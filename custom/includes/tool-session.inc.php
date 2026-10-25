<?php
/**
 * Shared helpers for the JSON tools in custom/tools/ (ratings.php,
 * nzbgeek.php): database connection, Spotweb session check and
 * per-user preference lookup.
 */

function tool_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function tool_connect_db(array $settings): PDO
{
    $engine = (string) ($settings['engine'] ?? 'mysql');
    if (in_array($engine, ['mysql', 'pdo_mysql'], true)) {
        $driver = 'mysql';
    } elseif (in_array($engine, ['pgsql', 'pdo_pgsql', 'postgres'], true)) {
        $driver = 'pgsql';
    } elseif (in_array($engine, ['sqlite', 'pdo_sqlite'], true)) {
        $pdo = new PDO('sqlite:' . ($settings['path'] ?? $settings['dbname'] ?? ''));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } else {
        $driver = $engine;
    }
    $host = $settings['host'] ?? '127.0.0.1';
    $port = $settings['port'] ?? ($driver === 'pgsql' ? '5432' : '3306');
    $dsn = sprintf('%s:host=%s;port=%s;dbname=%s', $driver, $host, $port, $settings['dbname'] ?? '');
    if ($driver === 'mysql') {
        $dsn .= ';charset=utf8mb4';
    }
    $pdo = new PDO($dsn, $settings['user'] ?? '', $settings['pass'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

/*
 * Boots the tool: JSON headers, database connection and a valid Spotweb
 * session ("spotsession" cookie, same check Spotweb itself performs).
 * Anonymous sessions are never stored in the sessions table, so the
 * configured nonauthenticated_userid is accepted too - such an install
 * exposes the same data publicly anyway.
 *
 * Returns [PDO, userid]; exits with a JSON error otherwise.
 */
function tool_boot(): array
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $dbFile = dirname(__DIR__, 2) . '/dbsettings.inc.php';
    if (!is_readable($dbFile)) {
        tool_out(['error' => 'Spotweb database settings are unavailable.'], 500);
    }
    require $dbFile;

    try {
        $pdo = tool_connect_db($dbsettings);
    } catch (Throwable $e) {
        tool_out(['error' => 'Unable to connect to the Spotweb database.'], 500);
    }

    $parts = explode('.', (string) ($_COOKIE['spotsession'] ?? ''));
    if (count($parts) !== 2 || $parts[0] === '' || !ctype_digit($parts[1])) {
        tool_out(['error' => 'A Spotweb login is required.'], 401);
    }
    $userId = (int) $parts[1];

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM sessions s INNER JOIN users u ON u.id = s.userid
            WHERE s.sessionid = ? AND s.userid = ? AND u.deleted = '0' LIMIT 1");
        $stmt->execute([$parts[0], $userId]);
        $authed = (bool) $stmt->fetchColumn();
        if (!$authed) {
            $stmt = $pdo->prepare("SELECT value, serialized FROM settings WHERE name = 'nonauthenticated_userid' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            $anonId = 0;
            if ($row) {
                $anonId = (int) $row['value'];
                if (!empty($row['serialized'])) {
                    $decoded = @unserialize((string) $row['value'], ['allowed_classes' => false]);
                    if (is_numeric($decoded)) {
                        $anonId = (int) $decoded;
                    }
                }
            }
            if ($anonId > 0 && $userId === $anonId) {
                $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND deleted = '0' LIMIT 1");
                $stmt->execute([$anonId]);
                $authed = (bool) $stmt->fetchColumn();
            }
        }
    } catch (Throwable $e) {
        $authed = false;
    }
    if (!$authed) {
        tool_out(['error' => 'A Spotweb login is required.'], 401);
    }

    return [$pdo, $userId];
}

/* The user's Spotweb preferences (usersettings.otherprefs) */
function tool_user_prefs(PDO $pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare('SELECT otherprefs FROM usersettings WHERE userid = ? LIMIT 1');
        $stmt->execute([$userId]);
        $prefs = @unserialize((string) $stmt->fetchColumn(), ['allowed_classes' => false]);
        return is_array($prefs) ? $prefs : [];
    } catch (Throwable $e) {
        return [];
    }
}

/*
 * API key lookup: the user's own key from Spotweb preferences
 * (edituserprefs page) wins, custom/config/<file>.php is the fallback.
 */
function tool_api_key(array $prefs, string $prefName, string $configFile, string $configKey): string
{
    $key = trim((string) ($prefs[$prefName] ?? ''));
    if ($key !== '') {
        return $key;
    }

    $cfgFile = dirname(__DIR__) . '/config/' . $configFile;
    $config = is_file($cfgFile) ? include $cfgFile : [];
    return is_array($config) ? trim((string) ($config[$configKey] ?? '')) : '';
}

/* NZB handlers that run server-side and can take an NZBGeek release */
const TOOL_SEND_HANDLERS = [
    'push-sabnzbd' => 'SABnzbd',
    'nzbget' => 'NZBGet',
    'nzbvortex' => 'NZBVortex',
    'save' => 'Save',
    'runcommand' => 'Run',
];
