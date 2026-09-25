<?php
$root = dirname(__DIR__, 2);
$dbFile = $root . '/dbsettings.inc.php';
if (!is_readable($dbFile)) {
    http_response_code(500);
    exit('Spotweb database settings are unavailable.');
}
require $dbFile;

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

function pass_salt(PDO $pdo): string
{
    try {
        $row = $pdo->query("SELECT value, serialized FROM settings WHERE name = 'pass_salt' LIMIT 1")->fetch();
        if (!$row) {
            return '';
        }
        $value = (string) $row['value'];
        if (!empty($row['serialized'])) {
            $decoded = @unserialize($value);
            if (is_string($decoded)) {
                $value = $decoded;
            }
        }
        return $value;
    } catch (Throwable $e) {
        return '';
    }
}

function valid_password(string $password, string $hash, string $salt): bool
{
    if ($hash === '') {
        return false;
    }
    $legacy = $salt === '' ? '' : sha1(strrev(substr($salt, 1, 3)) . $password . $salt);
    return ($legacy !== '' && hash_equals($hash, $legacy))
        || password_verify($password, $hash)
        || hash_equals($hash, sha1($password));
}

function setting_value(PDO $pdo, string $name)
{
    $stmt = $pdo->prepare('SELECT value, serialized FROM settings WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $value = $row['value'];
    if (!empty($row['serialized'])) {
        $decoded = @unserialize((string) $value);
        return $decoded === false && $value !== 'b:0;' ? null : $decoded;
    }
    return $value;
}

function tail_file(string $path, int $maxBytes = 131072, int $maxLines = 200): array
{
    if (!is_file($path)) {
        return ['state' => 'missing', 'content' => ''];
    }
    if (!is_readable($path)) {
        return ['state' => 'unreadable', 'content' => ''];
    }
    $size = filesize($path) ?: 0;
    $handle = fopen($path, 'rb');
    if (!$handle) {
        return ['state' => 'unreadable', 'content' => ''];
    }
    if ($size > $maxBytes) {
        fseek($handle, -$maxBytes, SEEK_END);
    }
    $data = stream_get_contents($handle);
    fclose($handle);
    $lines = preg_split('/\R/', (string) $data);
    return ['state' => 'ok', 'content' => implode("\n", array_slice($lines ?: [], -$maxLines))];
}

try {
    $pdo = connect_db($GLOBALS['dbsettings']);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Unable to connect to the Spotweb database.');
}

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
$error = '';
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: health-dashboard.php');
    exit;
}
if (isset($_POST['login'])) {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = $pdo->prepare('SELECT id, username, passhash FROM users WHERE username = ? AND deleted = 0 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && (int) $user['id'] === 2 && valid_password($password, (string) $user['passhash'], pass_salt($pdo))) {
        session_regenerate_id(true);
        $_SESSION['health_admin'] = true;
        $_SESSION['health_username'] = (string) $user['username'];
        header('Location: health-dashboard.php');
        exit;
    }
    $error = 'Invalid administrator credentials.';
}
if (empty($_SESSION['health_admin'])) {
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Spotweb Health</title><style>
*{box-sizing:border-box}body{margin:0;background:#0d1117;color:#e6edf3;font:14px system-ui;display:grid;place-items:center;min-height:100vh}.login{width:min(390px,calc(100% - 32px));background:#161b22;border:1px solid #30363d;border-radius:14px;padding:32px}h1{margin:0 0 8px;color:#58a6ff}.muted{color:#8b949e;margin-bottom:24px}label{display:block;margin:12px 0 5px}input,button{width:100%;padding:11px;border-radius:8px;border:1px solid #30363d;background:#0d1117;color:#e6edf3}button{margin-top:18px;background:#238636;border-color:#2ea043;font-weight:700;cursor:pointer}.error{color:#ff7b72;margin:10px 0}
</style></head><body><main class="login"><h1>Spotweb Health</h1><div class="muted">Administrator access required</div><?php if ($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?><form method="post"><label>Username</label><input name="username" value="admin" required autofocus><label>Password</label><input name="password" type="password" required><input type="hidden" name="login" value="1"><button>Sign in</button></form></main></body></html><?php
    exit;
}

$checks = [];
$add = static function (string $group, string $name, string $status, string $message) use (&$checks): void {
    $checks[] = compact('group', 'name', 'status', 'message');
};
$add('Runtime', 'PHP', version_compare(PHP_VERSION, '8.2', '>=') ? 'ok' : 'fail', PHP_VERSION . ' (' . PHP_SAPI . ')');
$add('Runtime', 'Platform', 'ok', PHP_OS_FAMILY . ' · ' . php_uname('m'));
$add('Runtime', 'Web server', 'ok', $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown');
$required = ['pdo', 'json', 'xml', 'mbstring', 'openssl', 'curl'];
$missing = array_values(array_filter($required, static fn($ext) => !extension_loaded($ext)));
$add('Runtime', 'Extensions', $missing ? 'fail' : 'ok', $missing ? 'Missing: ' . implode(', ', $missing) : 'All required extensions loaded');

foreach (['cache', 'logs'] as $directory) {
    $path = $root . '/' . $directory;
    $status = is_dir($path) && is_writable($path) ? 'ok' : 'fail';
    $add('Storage', ucfirst($directory), $status, is_dir($path) ? (is_writable($path) ? 'Writable' : 'Not writable') : 'Missing');
}
$free = @disk_free_space($root);
$total = @disk_total_space($root);
if ($free !== false && $total) {
    $percent = ($free / $total) * 100;
    $add('Storage', 'Free disk', $percent < 10 ? 'fail' : ($percent < 20 ? 'warn' : 'ok'), sprintf('%.1f GB free (%.1f%%)', $free / 1073741824, $percent));
}
$mode = substr(sprintf('%o', fileperms($dbFile)), -4);
$add('Security', 'Database settings', (int) $mode > 640 ? 'warn' : 'ok', 'Readable; mode ' . $mode);

try {
    $spots = (int) $pdo->query('SELECT COUNT(*) FROM spots')->fetchColumn();
    $users = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE deleted = 0')->fetchColumn();
    $add('Database', 'Connection', 'ok', 'Database query successful');
    $add('Database', 'Spots', $spots > 0 ? 'ok' : 'warn', number_format($spots) . ' indexed spots');
    $add('Database', 'Users', 'ok', $users . ' active users');
} catch (Throwable $e) {
    $add('Database', 'Connection', 'fail', 'Database query failed');
}

try {
    $row = $pdo->query("SELECT lastretrieved, nowrunning FROM usenetstate WHERE infotype = 'Spots' LIMIT 1")->fetch();
    $last = (int) ($row['lastretrieved'] ?? 0);
    $running = (int) ($row['nowrunning'] ?? 0);
    $age = $last > 0 ? (time() - $last) / 3600 : null;
    $add('Retrieval', 'Last retrieve', $age === null || $age > 24 ? 'warn' : 'ok', $last ? date('Y-m-d H:i:s', $last) . sprintf(' (%.1f hours ago)', $age) : 'No successful retrieval recorded');
    $add('Retrieval', 'Current state', $running > 0 && time() - $running > 7200 ? 'warn' : 'ok', $running > 0 ? 'Running since ' . date('Y-m-d H:i:s', $running) : 'Idle');
} catch (Throwable $e) {
    $add('Retrieval', 'State', 'fail', 'Unable to read retrieval state');
}

foreach (['nntp_hdr' => 'Header server', 'nntp_nzb' => 'NZB server'] as $key => $label) {
    try {
        $value = setting_value($pdo, $key);
        $host = is_array($value) ? trim((string) ($value['host'] ?? '')) : '';
        $port = is_array($value) ? (string) ($value['port'] ?? '') : '';
        $add('Connections', $label, $host === '' ? 'warn' : 'ok', $host === '' ? 'Not configured' : $host . ($port ? ':' . $port : ''));
    } catch (Throwable $e) {
        $add('Connections', $label, 'warn', 'Unable to read configuration');
    }
}
$add('Tooling', 'Retrieve script', is_readable($root . '/retrieve.php') ? 'ok' : 'fail', is_readable($root . '/retrieve.php') ? 'Available' : 'Missing');
$add('Tooling', 'Theme loader', is_readable($root . '/custom/includes/theme-loader.inc.php') ? 'ok' : 'warn', is_readable($root . '/custom/includes/theme-loader.inc.php') ? 'Installed' : 'Not installed');
$add('Tooling', 'CLI doctor', is_readable($root . '/bin/doctor.php') ? 'ok' : 'warn', is_readable($root . '/bin/doctor.php') ? 'Available' : 'Not installed');

$rank = ['ok' => 0, 'warn' => 1, 'fail' => 2];
$worst = 0;
foreach ($checks as $check) {
    $worst = max($worst, $rank[$check['status']] ?? 1);
}
$overall = ['Healthy', 'Warnings', 'Failures'][$worst];
$log = tail_file($root . '/logs/retrieve-cron.log');
$groups = [];
foreach ($checks as $check) {
    $groups[$check['group']][] = $check;
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Spotweb Health</title><style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;background:#0d1117;color:#e6edf3;font:14px system-ui}.wrap{max-width:1180px;margin:auto;padding:24px}.top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:22px}.top h1{margin:0;color:#58a6ff}.links{display:flex;gap:9px;flex-wrap:wrap}a,.button{color:#58a6ff;text-decoration:none;border:1px solid #30363d;border-radius:8px;padding:8px 11px;background:#161b22}.summary{padding:18px;border-radius:12px;margin-bottom:20px;border:1px solid #30363d;background:#161b22}.summary.ok{border-color:#238636}.summary.warn{border-color:#d29922}.summary.fail{border-color:#da3633}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px}.card{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:18px}.card h2{margin:0 0 12px;font-size:17px}.check{display:grid;grid-template-columns:12px 120px 1fr;gap:9px;padding:9px 0;border-top:1px solid #21262d}.check:first-of-type{border-top:0}.dot{width:10px;height:10px;border-radius:50%;margin-top:4px}.dot.ok{background:#3fb950}.dot.warn{background:#d29922}.dot.fail{background:#f85149}.name{font-weight:650}.message{color:#8b949e;overflow-wrap:anywhere}.logs{margin-top:18px}pre{background:#010409;border:1px solid #30363d;border-radius:9px;padding:14px;max-height:420px;overflow:auto;white-space:pre-wrap;font:12px ui-monospace,monospace}.muted{color:#8b949e}@media(max-width:620px){.wrap{padding:16px}.top{align-items:flex-start;flex-direction:column}.check{grid-template-columns:12px 100px 1fr}}
</style></head><body><main class="wrap"><header class="top"><div><h1>Spotweb Health</h1><div class="muted">Read-only system overview · <?php echo h(date('Y-m-d H:i:s')); ?></div></div><nav class="links"><a href="health-dashboard.php">Refresh</a><a href="filter-manager.php">Filters</a><a href="../../">Spotweb</a><a href="?logout=1">Logout</a></nav></header><section class="summary <?php echo ['ok','warn','fail'][$worst]; ?>"><strong><?php echo h($overall); ?></strong> · <?php echo count($checks); ?> checks completed</section><div class="grid"><?php foreach ($groups as $group => $items): ?><section class="card"><h2><?php echo h($group); ?></h2><?php foreach ($items as $check): ?><div class="check"><span class="dot <?php echo h($check['status']); ?>"></span><span class="name"><?php echo h($check['name']); ?></span><span class="message"><?php echo h($check['message']); ?></span></div><?php endforeach; ?></section><?php endforeach; ?></div><section class="card logs"><h2>Retrieval log</h2><?php if ($log['state'] === 'ok' && $log['content'] !== ''): ?><pre><?php echo h($log['content']); ?></pre><?php else: ?><p class="muted"><?php echo $log['state'] === 'missing' ? 'No retrieval log exists yet.' : ($log['state'] === 'unreadable' ? 'The retrieval log is not readable.' : 'The retrieval log is empty.'); ?></p><?php endif; ?></section></main></body></html>
