<?php
/**
 * NZBGeek search proxy for Spotweb.
 *
 * Called by custom/js/nzbgeek.js when the index page contains a search
 * query. Requires a valid Spotweb session (the "spotsession" cookie,
 * same check as custom/tools/ratings.php) and returns JSON only.
 *
 * Configure your NZBGeek API key (Dashboard -> API Key on nzbgeek.info)
 * in custom/config/nzbgeek.php.
 */
$root = dirname(__DIR__, 2);
$dbFile = $root . '/dbsettings.inc.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!is_readable($dbFile)) {
    http_response_code(500);
    exit(json_encode(['error' => 'Spotweb database settings are unavailable.']));
}
require $dbFile;

function out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function connect_db(array $settings): PDO
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

function http_get(string $url): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'SpotwebNzbGeek/1.0 (+https://github.com/VenimK/spotweb)',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($body === false || $code !== 200) {
        return null;
    }
    return (string) $body;
}

/* Cache table is shared with ratings.php */
function cache_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo->exec('CREATE TABLE IF NOT EXISTS rating_cache (
        cachekey VARCHAR(80) PRIMARY KEY,
        payload TEXT,
        fetched BIGINT NOT NULL
    )');
}

function cache_get(PDO $pdo, string $key): ?array
{
    try {
        cache_table($pdo);
        $stmt = $pdo->prepare('SELECT payload, fetched FROM rating_cache WHERE cachekey = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $decoded = json_decode((string) $row['payload'], true);
        if (!is_array($decoded)) {
            return null;
        }
        $ttl = (int) ($decoded['_ttl'] ?? 120);
        if ((time() - (int) $row['fetched']) < $ttl) {
            unset($decoded['_ttl']);
            return $decoded;
        }
    } catch (Throwable $e) {
        // cache unavailable - fall through to a live lookup
    }
    return null;
}

function cache_put(PDO $pdo, string $key, array $payload, int $ttl): void
{
    try {
        cache_table($pdo);
        $payload['_ttl'] = $ttl;
        $stmt = $pdo->prepare('DELETE FROM rating_cache WHERE cachekey = ?');
        $stmt->execute([$key]);
        $stmt = $pdo->prepare('INSERT INTO rating_cache (cachekey, payload, fetched) VALUES (?, ?, ?)');
        $stmt->execute([$key, json_encode($payload), time()]);
    } catch (Throwable $e) {
        // non-fatal
    }
}

/* ------------------------------------------------------------------ */

try {
    $pdo = connect_db($GLOBALS['dbsettings']);
} catch (Throwable $e) {
    out(['error' => 'Unable to connect to the Spotweb database.'], 500);
}

/* Same session check as ratings.php: valid session, or anonymous browsing */
$sessionCookie = (string) ($_COOKIE['spotsession'] ?? '');
$sessionParts = explode('.', $sessionCookie);
$authed = false;
if (count($sessionParts) === 2 && $sessionParts[0] !== '' && ctype_digit($sessionParts[1])) {
    $cookieUserId = (int) $sessionParts[1];
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM sessions s INNER JOIN users u ON u.id = s.userid
            WHERE s.sessionid = ? AND s.userid = ? AND u.deleted = '0' LIMIT 1");
        $stmt->execute([$sessionParts[0], $cookieUserId]);
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
            if ($anonId > 0 && $cookieUserId === $anonId) {
                $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND deleted = '0' LIMIT 1");
                $stmt->execute([$anonId]);
                $authed = (bool) $stmt->fetchColumn();
            }
        }
    } catch (Throwable $e) {
        $authed = false;
    }
}
if (!$authed) {
    out(['error' => 'A Spotweb login is required.'], 401);
}

/* NZBGeek API key */
$cfgFile = dirname(__DIR__) . '/config/nzbgeek.php';
$config = is_file($cfgFile) ? include $cfgFile : [];
$apiKey = is_array($config) ? trim((string) ($config['api_key'] ?? '')) : '';
$baseUrl = is_array($config) ? rtrim((string) ($config['base_url'] ?? 'https://api.nzbgeek.info'), '/') : 'https://api.nzbgeek.info';
if ($apiKey === '') {
    out(['error' => 'NZBGeek is not configured (missing api_key in custom/config/nzbgeek.php).'], 400);
}

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2 || mb_strlen($q) > 200) {
    out(['error' => 'Missing or invalid query.'], 400);
}
$cat = preg_replace('/[^0-9,]/', '', (string) ($_GET['cat'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 25);
$limit = max(1, min(100, $limit));

$cacheKey = 'nzbgeek:' . md5(mb_strtolower($q) . '|' . $cat . '|' . $limit);
$cached = cache_get($pdo, $cacheKey);
if ($cached !== null) {
    out($cached + ['cached' => true]);
}

$url = $baseUrl . '/api?t=search&apikey=' . rawurlencode($apiKey)
    . '&q=' . rawurlencode($q) . '&limit=' . $limit
    . ($cat !== '' ? '&cat=' . $cat : '')
    . '&extended=1';
$body = http_get($url);
if ($body === null) {
    out(['error' => 'NZBGeek is unreachable.'], 502);
}
if (strpos($body, 'code="100"') !== false || stripos($body, 'incorrect user credentials') !== false) {
    out(['error' => 'NZBGeek rejected the API key.'], 502);
}

$xml = @simplexml_load_string($body);
if ($xml === false) {
    out(['error' => 'Unexpected response from NZBGeek.'], 502);
}

$total = 0;
$responseNode = $xml->xpath('//newznab:response');
if (!empty($responseNode)) {
    $total = (int) $responseNode[0]['total'];
}

$items = [];
foreach ($xml->xpath('//channel/item') ?: [] as $item) {
    $attrs = [];
    foreach ($item->xpath('newznab:attr') ?: [] as $attr) {
        $attrs[(string) $attr['name']] = (string) $attr['value'];
    }
    $size = (int) ($attrs['size'] ?? 0);
    if ($size === 0 && isset($item->enclosure)) {
        $size = (int) $item->enclosure['length'];
    }
    $link = (string) $item->link;
    if ($link === '') {
        $link = (string) $item->guid;
    }
    $items[] = [
        'title' => (string) $item->title,
        'nzb' => $link,
        'guid' => (string) $item->guid,
        'date' => strtotime((string) $item->pubDate) ?: null,
        'size' => $size,
        'category' => (string) $item->category ?: ($attrs['category'] ?? ''),
        'poster' => $attrs['poster'] ?? '',
        'grabs' => (int) ($attrs['grabs'] ?? 0),
    ];
}

$response = ['total' => $total, 'items' => $items];
cache_put($pdo, $cacheKey, $response, 120);
out($response);
