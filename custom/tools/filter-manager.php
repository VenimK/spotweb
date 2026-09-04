<?php
/**
 * Spotweb Filter Manager
 *
 * Add, edit, nest, reorder, and delete sidebar filters without the
 * advanced-search dialog. Filters are written to the same `filters`
 * table Spotweb's sidebar reads.
 *
 * Usage:  http://your-spotweb/custom/tools/filter-manager.php
 * Login:  your Spotweb account (not a hardcoded default).
 */

$spotwebRoot = dirname(__DIR__, 2);

$dbsettingsFile = $spotwebRoot . '/dbsettings.inc.php';
if (!file_exists($dbsettingsFile)) {
    http_response_code(500);
    echo '<h2>Spotweb dbsettings.inc.php not found.</h2><p>Install and configure Spotweb first.</p>';
    exit;
}
require_once $dbsettingsFile;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function js_str($s): string
{
    return json_encode((string) $s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

function redirect(array $params = []): void
{
    $target = 'filter-manager.php';
    if ($params) {
        $target .= '?' . http_build_query($params);
    }
    header('Location: ' . $target);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['fm_flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['fm_flash'] ?? null;
    unset($_SESSION['fm_flash']);
    return is_array($flash) ? $flash : null;
}

function require_csrf(): void
{
    $token = (string) ($_POST['csrf'] ?? '');
    $expected = (string) ($_SESSION['fm_csrf'] ?? '');
    if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
        flash('Session expired. Please try again.', 'error');
        redirect();
    }
}

function pass_to_hash(string $salt, string $password): string
{
    return sha1(strrev(substr($salt, 1, 3)) . $password . $salt);
}

function connect_pdo(array $dbsettings): PDO
{
    $engine = (string) ($dbsettings['engine'] ?? 'mysql');
    if (in_array($engine, ['mysql', 'pdo_mysql'], true)) {
        $driver = 'mysql';
    } elseif (in_array($engine, ['pdo_pgsql', 'pgsql', 'postgres'], true)) {
        $driver = 'pgsql';
    } elseif (in_array($engine, ['pdo_sqlite', 'sqlite'], true)) {
        $path = $dbsettings['path'] ?? ($dbsettings['dbname'] ?? '');
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } else {
        $driver = $engine;
    }

    $host = $dbsettings['host'] ?? '127.0.0.1';
    $port = $dbsettings['port'] ?? ($driver === 'pgsql' ? '5432' : '3306');
    $dbname = $dbsettings['dbname'] ?? '';
    $dsn = sprintf('%s:host=%s;port=%s;dbname=%s', $driver, $host, $port, $dbname);
    if ($driver === 'mysql') {
        $dsn .= ';charset=utf8mb4';
    }

    $pdo = new PDO($dsn, $dbsettings['user'] ?? '', $dbsettings['pass'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function field_store_name(string $uiName): string
{
    $map = [
        'Title'     => 'Titel',
        'Titel'     => 'Titel',
        'Tag'       => 'Tag',
        'Poster'    => 'Poster',
        'SpotterID' => 'SpotterID',
    ];
    return $map[$uiName] ?? 'Titel';
}

function field_ui_name(string $storeName): string
{
    $map = [
        'Titel'     => 'Title',
        'Title'     => 'Title',
        'Tag'       => 'Tag',
        'Poster'    => 'Poster',
        'SpotterID' => 'SpotterID',
    ];
    return $map[$storeName] ?? 'Title';
}

function field_label(string $storeOrUi): string
{
    $map = [
        'Titel'     => 'Title',
        'Title'     => 'Title',
        'Tag'       => 'Tag',
        'Poster'    => 'Poster',
        'SpotterID' => 'Spotter ID',
    ];
    return $map[$storeOrUi] ?? $storeOrUi;
}

function encode_valuelist(string $field, string $searchText, string $age): string
{
    $parts = [];
    $searchText = trim($searchText);
    if ($searchText !== '') {
        $parts[] = rawurlencode($field . ':=:DEF:' . $searchText);
    }
    $age = trim($age);
    if ($age !== '') {
        $parts[] = rawurlencode($age);
    }
    return implode('&', $parts);
}

function parse_valuelist(?string $vl): array
{
    $out = ['search_type' => 'Title', 'search_text' => '', 'age' => '', 'raw' => []];
    $vl = trim((string) $vl);
    if ($vl === '' || strtoupper($vl) === 'NULL') {
        return $out;
    }
    foreach (explode('&', $vl) as $part) {
        $part = trim(rawurldecode($part));
        if ($part === '') {
            continue;
        }
        $out['raw'][] = $part;
        $pieces = explode(':', $part, 4);
        if (count($pieces) < 4) {
            continue;
        }
        [$field, $op, $bool, $value] = $pieces;
        unset($op, $bool);
        if (strcasecmp($field, 'date') === 0) {
            $out['age'] = $part;
            continue;
        }
        $out['search_type'] = field_ui_name($field);
        $out['search_text'] = $value;
    }
    return $out;
}

function valuelist_to_text(?string $vl): string
{
    $parsed = parse_valuelist($vl);
    $bits = [];
    if ($parsed['search_text'] !== '') {
        $bits[] = field_label($parsed['search_type']) . ': ' . $parsed['search_text'];
    }
    if ($parsed['age'] !== '') {
        $bits[] = 'age ' . preg_replace('/^date:>:DEF:/', '', $parsed['age']);
    }
    foreach ($parsed['raw'] as $raw) {
        if (stripos($raw, 'Titel:=') === 0 || stripos($raw, 'Title:=') === 0
            || stripos($raw, 'Tag:=') === 0 || stripos($raw, 'Poster:=') === 0
            || stripos($raw, 'SpotterID:=') === 0 || stripos($raw, 'date:') === 0) {
            continue;
        }
        $bits[] = $raw;
    }
    return implode(', ', $bits);
}

function category_presets(): array
{
    return [
        '' => 'All categories',
        'cat0' => 'Image — all',
        'cat0_z0' => 'Movies',
        'cat0_z0_a15' => 'UHD / 4K movies',
        'cat0_z0_a4,cat0_z0_a6,cat0_z0_a7,cat0_z0_a8,cat0_z0_a9,cat0_z0_a15' => 'HD movies',
        'cat0_z0_a6' => 'Blu-ray',
        'cat0_z0_a0' => 'DivX / xvid',
        'cat0_z1' => 'Series',
        'cat0_z2' => 'Books',
        'cat0_z3' => 'Erotica',
        'cat0_z4' => 'Pictures',
        'cat1' => 'Audio / Music',
        'cat1_a0,cat1_a3,cat1_a5,cat1_a6' => 'Music — compressed',
        'cat1_a2,cat1_a4,cat1_a7,cat1_a8' => 'Music — lossless',
        'cat2' => 'Games',
        'cat2_a0' => 'Games — Windows',
        'cat2_a1,cat2_a2' => 'Games — Mac / Linux',
        'cat2_a3,cat2_a4,cat2_a5,cat2_a12' => 'Games — PlayStation',
        'cat2_a6,cat2_a7' => 'Games — Xbox',
        'cat2_a8,cat2_a9,cat2_a10,cat2_a11' => 'Games — Nintendo',
        'cat3' => 'Applications',
        'cat3_a0' => 'Apps — Windows',
        'cat3_a1,cat3_a2,cat3_a3' => 'Apps — Mac / Linux',
        'cat3_a6' => 'Apps — iOS',
        'cat3_a7' => 'Apps — Android',
    ];
}

function tree_to_name(?string $tree): string
{
    $tree = trim((string) $tree);
    if ($tree === '') {
        return 'All';
    }
    $map = category_presets();
    if (isset($map[$tree])) {
        return $map[$tree];
    }
    if (isset($map[rtrim($tree, ',')])) {
        return $map[rtrim($tree, ',')];
    }
    if (str_starts_with($tree, '~')) {
        return 'NOT ' . tree_to_name(substr($tree, 1));
    }
    $friendly = [
        'cat0_z0' => 'Movies',
        'cat0_z1' => 'Series',
        'cat0_z2' => 'Books',
        'cat0_z3' => 'Erotica',
        'cat0_z4' => 'Pictures',
        'cat0'    => 'Image',
        'cat1'    => 'Audio',
        'cat2'    => 'Games',
        'cat3'    => 'Apps',
    ];
    if (isset($friendly[$tree])) {
        return $friendly[$tree];
    }
    return $tree;
}

function filter_icons(): array
{
    return [
        'custom'       => 'Plain',
        'film'         => 'Movie',
        'tv'           => 'TV / Series',
        'hd'           => 'HD / UHD',
        'bluray'       => 'Blu-ray',
        'dvd'          => 'DVD',
        'divx'         => 'DivX',
        'wmv'          => 'WMV',
        'mpg'          => 'MPEG',
        'book'         => 'Book',
        'picture'      => 'Picture',
        'female'       => 'Erotica',
        'music'        => 'Music',
        'controller'   => 'Game',
        'windows'      => 'Windows',
        'vista'        => 'Vista / Win app',
        'linux'        => 'Linux',
        'mac'          => 'Apple',
        'playstation'  => 'PlayStation',
        'xbox'         => 'Xbox',
        'nintendo_ds'  => 'Nintendo DS',
        'nintendo_wii' => 'Nintendo Wii',
        'application'  => 'Application',
        'phone'        => 'Phone',
        'pda'          => 'PDA / Phone',
        'ipod'         => 'iPod / Nav',
        'download'     => 'Download',
        'fav'          => 'Favorite',
        'tag'          => 'Tag',
        'home'         => 'Home',
        'today'        => 'Today',
        'eye'          => 'Eye',
        'stats'        => 'Stats',
        'config'       => 'Config',
    ];
}

function default_sortorder(string $sorton): string
{
    return in_array($sorton, ['title', 'poster'], true) ? 'ASC' : 'DESC';
}

function spotweb_base_url(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/custom/tools/filter-manager.php'));
    $dir = rtrim(dirname($script), '/');
    $root = dirname($dir); // /custom
    $root = dirname($root); // spotweb root
    if ($root === '/' || $root === '.' || $root === '\\') {
        return '';
    }
    return rtrim($root, '/');
}

function filter_preview_url(array $filter): string
{
    $query = ['search' => ['tree' => (string) ($filter['tree'] ?? '')]];
    $vl = trim((string) ($filter['valuelist'] ?? ''));
    if ($vl !== '' && strtoupper($vl) !== 'NULL') {
        foreach (explode('&', $vl) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $query['search']['value'][] = rawurldecode($part);
            }
        }
    }
    if (!empty($filter['sorton'])) {
        $query['sortby'] = $filter['sorton'];
        $query['sortdir'] = $filter['sortorder'] ?: default_sortorder((string) $filter['sorton']);
    }
    return spotweb_base_url() . '/?' . http_build_query($query);
}

function fetch_filters(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT id, userid, filtertype, title, icon, torder, tparent, tree, valuelist, sorton, sortorder, enablenotify
         FROM filters
         WHERE userid = ? AND filtertype = 'filter'
         ORDER BY tparent ASC, torder ASC, id ASC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function index_by_id(array $filters): array
{
    $byId = [];
    foreach ($filters as $f) {
        $byId[(int) $f['id']] = $f;
    }
    return $byId;
}

function flatten_tree(array $filters): array
{
    $children = [];
    foreach ($filters as $f) {
        $children[(int) $f['tparent']][] = $f;
    }
    $walk = function (int $parent, int $depth) use (&$walk, &$children): array {
        $out = [];
        $siblings = $children[$parent] ?? [];
        $count = count($siblings);
        foreach ($siblings as $i => $row) {
            $id = (int) $row['id'];
            $row['_depth'] = $depth;
            $row['_index'] = $i;
            $row['_count'] = $count;
            $row['_childcount'] = count($children[$id] ?? []);
            $out[] = $row;
            $out = array_merge($out, $walk($id, $depth + 1));
        }
        return $out;
    };
    return $walk(0, 0);
}

function descendant_ids(array $byId, int $id): array
{
    $ids = [];
    foreach ($byId as $row) {
        if ((int) $row['tparent'] === $id) {
            $cid = (int) $row['id'];
            $ids[] = $cid;
            $ids = array_merge($ids, descendant_ids($byId, $cid));
        }
    }
    return $ids;
}

function would_cycle(array $byId, int $filterId, int $newParent): bool
{
    if ($newParent === 0) {
        return false;
    }
    if ($newParent === $filterId) {
        return true;
    }
    $cur = $newParent;
    $guard = 0;
    while ($cur && $guard++ < 200) {
        if ($cur === $filterId) {
            return true;
        }
        $cur = isset($byId[$cur]) ? (int) $byId[$cur]['tparent'] : 0;
    }
    return false;
}

function next_torder(PDO $pdo, int $userId, int $parent): int
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(MAX(torder), -1) + 1 FROM filters WHERE userid = ? AND tparent = ? AND filtertype = 'filter'"
    );
    $stmt->execute([$userId, $parent]);
    return (int) $stmt->fetchColumn();
}

function copy_filters_from(PDO $pdo, int $srcUser, int $dstUser): int
{
    $pdo->prepare("DELETE FROM filters WHERE userid = ? AND filtertype = 'filter'")->execute([$dstUser]);
    $stmt = $pdo->prepare(
        "SELECT id, title, icon, torder, tparent, tree, valuelist, sorton, sortorder, enablenotify
         FROM filters WHERE userid = ? AND filtertype = 'filter' ORDER BY id ASC"
    );
    $stmt->execute([$srcUser]);
    $rows = $stmt->fetchAll();
    $idMap = [0 => 0];
    $remaining = $rows;
    $inserted = 0;
    $guard = 0;
    $insert = $pdo->prepare(
        "INSERT INTO filters (userid, filtertype, title, icon, torder, tparent, tree, valuelist, sorton, sortorder, enablenotify)
         VALUES (?, 'filter', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    while ($remaining && $guard++ < 1000) {
        $next = [];
        foreach ($remaining as $row) {
            $oldParent = (int) $row['tparent'];
            if (!array_key_exists($oldParent, $idMap)) {
                $next[] = $row;
                continue;
            }
            $insert->execute([
                $dstUser,
                $row['title'],
                $row['icon'] ?: 'custom',
                (int) $row['torder'],
                $idMap[$oldParent],
                (string) ($row['tree'] ?? ''),
                (string) ($row['valuelist'] ?? ''),
                (string) ($row['sorton'] ?? ''),
                (string) ($row['sortorder'] ?? ''),
                (int) $row['enablenotify'],
            ]);
            $idMap[(int) $row['id']] = (int) $pdo->lastInsertId();
            $inserted++;
        }
        if (count($next) === count($remaining)) {
            break;
        }
        $remaining = $next;
    }
    return $inserted;
}

function read_pass_salt(PDO $pdo): string
{
    try {
        $stmt = $pdo->query("SELECT value, serialized FROM settings WHERE name = 'pass_salt' LIMIT 1");
        $row = $stmt ? $stmt->fetch() : false;
        if (!$row) {
            return '';
        }
        $value = (string) $row['value'];
        if (!empty($row['serialized'])) {
            $un = @unserialize($value);
            if (is_string($un)) {
                $value = $un;
            }
        }
        return $value;
    } catch (Exception $e) {
        return '';
    }
}

function verify_spotweb_password(string $password, string $dbHash, string $passSalt): bool
{
    if ($dbHash === '') {
        return false;
    }
    if ($passSalt !== '' && hash_equals($dbHash, pass_to_hash($passSalt, $password))) {
        return true;
    }
    if (password_verify($password, $dbHash)) {
        return true;
    }
    if (hash_equals($dbHash, sha1($password))) {
        return true;
    }
    return false;
}

function show_login(string $error = '', array $debugInfo = []): void
{
    $debug = $debugInfo;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Spotweb Filter Manager — Login</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; color: #e0e0e0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
  .login { background: #16213e; border-radius: 12px; padding: 40px; width: 380px; max-width: calc(100vw - 32px); border: 1px solid #1a1a3e; }
  h1 { color: #0fbcf9; margin-bottom: 8px; font-size: 22px; }
  .subtitle { color: #888; margin-bottom: 24px; font-size: 13px; }
  label { display: block; margin-bottom: 4px; color: #aaa; font-size: 13px; }
  input { width: 100%; padding: 10px 12px; border: 1px solid #2a2a4e; border-radius: 8px; background: #0d1117; color: #e0e0e0; font-size: 14px; margin-bottom: 12px; }
  input:focus { outline: none; border-color: #0fbcf9; }
  button { width: 100%; padding: 12px; border: none; border-radius: 8px; background: #0fbcf9; color: #0d1117; font-size: 14px; font-weight: 600; cursor: pointer; }
  button:hover { background: #0ea5e9; }
  .error { color: #e74c3c; font-size: 13px; margin-bottom: 12px; }
  .debug { margin-top: 20px; padding: 16px; background: #0d1117; border-radius: 8px; border: 1px solid #2a2a4e; font-family: ui-monospace, monospace; font-size: 12px; color: #0fbcf9; white-space: pre-wrap; word-break: break-all; max-height: 280px; overflow-y: auto; }
  .debug h3 { color: #e0e0e0; margin-bottom: 8px; font-family: -apple-system, sans-serif; }
  .debug-hint { margin-top: 16px; font-size: 12px; color: #666; text-align: center; }
  .debug-hint a { color: #0fbcf9; text-decoration: none; }
</style>
</head>
<body>
<div class="login">
  <h1>Spotweb Filter Manager</h1>
  <div class="subtitle">Sign in with your Spotweb username and password</div>
<?php if ($error): ?>
  <div class="error"><?php echo h($error); ?></div>
<?php endif; ?>
  <form method="post" action="filter-manager.php" autocomplete="on">
    <label for="username">Username</label>
    <input id="username" type="text" name="username" value="admin" required autofocus>
    <label for="password">Password</label>
    <input id="password" type="password" name="password" required>
    <input type="hidden" name="login" value="1">
    <button type="submit">Login</button>
  </form>
<?php if ($debug): ?>
  <div class="debug">
    <h3>Diagnostics</h3>
<?php foreach ($debug as $line): ?>
    <?php echo h($line); ?>

<?php endforeach; ?>
  </div>
<?php endif; ?>
  <div class="debug-hint">Login not working? Open <a href="filter-manager.php?debug=1">?debug=1</a></div>
</div>
</body>
</html>
    <?php
}

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
try {
    $pdo = connect_pdo($GLOBALS['dbsettings']);
} catch (Exception $e) {
    http_response_code(500);
    echo '<h2>Database connection failed</h2><pre>' . h($e->getMessage()) . '</pre>';
    exit;
}

$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';
$debugInfo = [];
if ($debugMode) {
    $debugInfo[] = 'Spotweb root: ' . $spotwebRoot;
    $debugInfo[] = 'DB engine: ' . ($GLOBALS['dbsettings']['engine'] ?? 'unknown');
    $debugInfo[] = 'DB host: ' . ($GLOBALS['dbsettings']['host'] ?? 'unknown');
    $debugInfo[] = 'DB name: ' . ($GLOBALS['dbsettings']['dbname'] ?? 'unknown');
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll();
        $debugInfo[] = 'Users columns: ' . implode(', ', array_column($cols, 'Field'));
    } catch (Exception $e) {
        $debugInfo[] = 'Cannot read users table: ' . $e->getMessage();
    }
    try {
        $salt = read_pass_salt($pdo);
        $debugInfo[] = $salt !== '' ? 'pass_salt present (len ' . strlen($salt) . ')' : 'pass_salt missing';
        $users = $pdo->query('SELECT id, username, deleted FROM users ORDER BY id')->fetchAll();
        $debugInfo[] = 'Users: ' . count($users);
        foreach ($users as $u) {
            $debugInfo[] = sprintf('  id=%d %s deleted=%s', $u['id'], $u['username'], $u['deleted']);
        }
        $stmt = $pdo->prepare('SELECT passhash FROM users WHERE username = ? LIMIT 1');
        $stmt->execute(['admin']);
        $adminHash = (string) $stmt->fetchColumn();
        if ($salt && $adminHash) {
            $debugInfo[] = 'admin / spotweb hash match: ' . (hash_equals($adminHash, pass_to_hash($salt, 'spotweb')) ? 'YES' : 'NO');
        }
    } catch (Exception $e) {
        $debugInfo[] = 'Diagnostics failed: ' . $e->getMessage();
    }
}

// ---------------------------------------------------------------------------
// Session / auth
// ---------------------------------------------------------------------------
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
if (empty($_SESSION['fm_csrf'])) {
    $_SESSION['fm_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['fm_csrf'];

$isAuth = !empty($_SESSION['filter_mgr_auth']);
$loginError = '';

if (isset($_POST['login'])) {
    $u = trim((string) ($_POST['username'] ?? ''));
    $p = (string) ($_POST['password'] ?? '');
    $loginError = 'Invalid username or password';
    try {
        $stmt = $pdo->prepare('SELECT id, username, passhash FROM users WHERE username = ? AND deleted = 0 LIMIT 1');
        $stmt->execute([$u]);
        $user = $stmt->fetch();
        if ($user && strcasecmp((string) $user['username'], 'anonymous') !== 0) {
            $salt = read_pass_salt($pdo);
            if (verify_spotweb_password($p, (string) $user['passhash'], $salt)) {
                session_regenerate_id(true);
                $_SESSION['fm_csrf'] = bin2hex(random_bytes(16));
                $_SESSION['filter_mgr_auth'] = true;
                $_SESSION['filter_mgr_userid'] = (int) $user['id'];
                $_SESSION['filter_mgr_username'] = (string) $user['username'];
                $_SESSION['filter_mgr_target'] = (int) $user['id'];
                $isAuth = true;
                $loginError = '';
                redirect();
            }
        }
    } catch (Exception $e) {
        $loginError = 'Login failed: ' . $e->getMessage();
    }
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
    header('Location: filter-manager.php');
    exit;
}

if (!$isAuth) {
    show_login($loginError, $debugInfo);
    exit;
}

$authUserId = (int) ($_SESSION['filter_mgr_userid'] ?? 0);
$username = (string) ($_SESSION['filter_mgr_username'] ?? '');
$isAdmin = ($authUserId === 2);
if ($authUserId < 1) {
    session_destroy();
    redirect();
}

if (isset($_POST['action']) && $_POST['action'] === 'switch_user' && $isAdmin) {
    require_csrf();
    $target = (int) ($_POST['target_userid'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ? AND deleted = 0 LIMIT 1');
    $stmt->execute([$target]);
    $row = $stmt->fetch();
    if ($row) {
        $_SESSION['filter_mgr_target'] = (int) $row['id'];
        flash('Now editing filters for ' . $row['username']);
    } else {
        flash('Unknown user', 'error');
    }
    redirect();
}

$userId = (int) ($_SESSION['filter_mgr_target'] ?? $authUserId);
if (!$isAdmin) {
    $userId = $authUserId;
}

$allUsers = [];
if ($isAdmin) {
    try {
        $allUsers = $pdo->query('SELECT id, username FROM users WHERE deleted = 0 ORDER BY id')->fetchAll();
    } catch (Exception $e) {
        $allUsers = [];
    }
}

$targetName = $username;
foreach ($allUsers as $u) {
    if ((int) $u['id'] === $userId) {
        $targetName = (string) $u['username'];
        break;
    }
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$highlightId = isset($_GET['highlight']) ? (int) $_GET['highlight'] : 0;

$allowedIcons = array_keys(filter_icons());
$allowedSorton = ['stamp', 'title', 'poster', 'spotrating', 'commentcount'];
$allowedSortorder = ['ASC', 'DESC'];
$presetTrees = array_keys(category_presets());
$ageOptions = [
    ''                   => 'Any age',
    'date:>:DEF:-1 day'  => 'Last 1 day',
    'date:>:DEF:-3 days' => 'Last 3 days',
    'date:>:DEF:-1 week' => 'Last 1 week',
    'date:>:DEF:-2 weeks'=> 'Last 2 weeks',
    'date:>:DEF:-1 month'=> 'Last 1 month',
    'date:>:DEF:-3 months'=> 'Last 3 months',
    'date:>:DEF:-6 months'=> 'Last 6 months',
    'date:>:DEF:-1 year' => 'Last 1 year',
];

function sanitize_icon(string $icon, array $allowed): string
{
    $legacy = ['filter' => 'custom', 'image' => 'picture', 'star' => 'fav', 'heart' => 'fav'];
    if (isset($legacy[$icon])) {
        $icon = $legacy[$icon];
    }
    return in_array($icon, $allowed, true) ? $icon : 'custom';
}

function read_filter_form(array $src, array $allowedIcons, array $allowedSorton, array $allowedSortorder): array
{
    $title = trim((string) ($src['title'] ?? ''));
    $searchType = field_store_name((string) ($src['search_type'] ?? 'Title'));
    $searchText = trim((string) ($src['search_text'] ?? ''));
    $age = (string) ($src['age'] ?? '');
    $tree = trim((string) ($src['category'] ?? ''));
    $customTree = trim((string) ($src['custom_tree'] ?? ''));
    if ($customTree !== '') {
        $tree = $customTree;
    }
    $tree = preg_replace('/[^a-z0-9_,~!]/i', '', $tree) ?? '';
    $icon = sanitize_icon(trim((string) ($src['icon'] ?? 'custom')), $allowedIcons);
    $sorton = (string) ($src['sorton'] ?? 'stamp');
    if (!in_array($sorton, $allowedSorton, true)) {
        $sorton = 'stamp';
    }
    $sortorder = strtoupper((string) ($src['sortorder'] ?? ''));
    if (!in_array($sortorder, $allowedSortorder, true)) {
        $sortorder = default_sortorder($sorton);
    }
    $parent = (int) ($src['tparent'] ?? 0);
    if ($parent < 0) {
        $parent = 0;
    }
    $notify = !empty($src['enablenotify']) ? 1 : 0;
    $allowedAges = [
        '', 'date:>:DEF:-1 day', 'date:>:DEF:-3 days', 'date:>:DEF:-1 week',
        'date:>:DEF:-2 weeks', 'date:>:DEF:-1 month', 'date:>:DEF:-3 months',
        'date:>:DEF:-6 months', 'date:>:DEF:-1 year',
    ];
    if (!in_array($age, $allowedAges, true)) {
        $age = '';
    }
    return [
        'title'         => $title,
        'tree'          => $tree,
        'valuelist'     => encode_valuelist($searchType, $searchText, $age),
        'icon'          => $icon,
        'sorton'        => $sorton,
        'sortorder'     => $sortorder,
        'tparent'       => $parent,
        'enablenotify'  => $notify,
        'search_type'   => field_ui_name($searchType),
        'search_text'   => $searchText,
        'age'           => $age,
    ];
}

// ---------------------------------------------------------------------------
// Mutations
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'login' && $_POST['action'] !== 'switch_user') {
    require_csrf();
    $action = (string) $_POST['action'];

    try {
        if ($action === 'add' || $action === 'update') {
            $form = read_filter_form($_POST, $allowedIcons, $allowedSorton, $allowedSortorder);
            if (strlen($form['title']) < 2) {
                flash('Filter name must be at least 2 characters', 'error');
                redirect($action === 'update' ? ['edit' => (int) ($_POST['filter_id'] ?? 0)] : []);
            }
            $existing = fetch_filters($pdo, $userId);
            $byId = index_by_id($existing);
            $parent = $form['tparent'];
            if ($parent !== 0 && !isset($byId[$parent])) {
                flash('Parent filter not found', 'error');
                redirect();
            }

            if ($action === 'add') {
                $torder = next_torder($pdo, $userId, $parent);
                $stmt = $pdo->prepare(
                    'INSERT INTO filters (userid, filtertype, title, icon, torder, tparent, tree, valuelist, sorton, sortorder, enablenotify)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $userId, 'filter', $form['title'], $form['icon'], $torder, $parent,
                    $form['tree'], $form['valuelist'], $form['sorton'], $form['sortorder'], $form['enablenotify'],
                ]);
                $newId = (int) $pdo->lastInsertId();
                flash("Filter '{$form['title']}' added");
                redirect(['highlight' => $newId]);
            }

            $filterId = (int) ($_POST['filter_id'] ?? 0);
            if ($filterId < 1 || !isset($byId[$filterId])) {
                flash('Filter not found', 'error');
                redirect();
            }
            if (would_cycle($byId, $filterId, $parent)) {
                flash('A filter cannot be nested under itself', 'error');
                redirect(['edit' => $filterId]);
            }
            $current = $byId[$filterId];
            $torder = (int) $current['torder'];
            if ($parent !== (int) $current['tparent']) {
                $torder = next_torder($pdo, $userId, $parent);
            }
            $stmt = $pdo->prepare(
                'UPDATE filters
                 SET title = ?, icon = ?, torder = ?, tparent = ?, tree = ?, valuelist = ?, sorton = ?, sortorder = ?, enablenotify = ?
                 WHERE userid = ? AND id = ? AND filtertype = ?'
            );
            $stmt->execute([
                $form['title'], $form['icon'], $torder, $parent, $form['tree'], $form['valuelist'],
                $form['sorton'], $form['sortorder'], $form['enablenotify'],
                $userId, $filterId, 'filter',
            ]);
            flash("Filter '{$form['title']}' updated");
            redirect(['highlight' => $filterId]);
        }

        if ($action === 'delete') {
            $filterId = (int) ($_POST['filter_id'] ?? 0);
            $existing = fetch_filters($pdo, $userId);
            $byId = index_by_id($existing);
            if ($filterId < 1 || !isset($byId[$filterId])) {
                flash('Filter not found', 'error');
                redirect();
            }
            $ids = array_merge([$filterId], descendant_ids($byId, $filterId));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$userId], $ids);
            $pdo->prepare("DELETE FROM filters WHERE userid = ? AND id IN ($placeholders)")->execute($params);
            $n = count($ids);
            flash($n === 1 ? 'Filter deleted' : "Deleted $n filters (including nested)");
            redirect();
        }

        if ($action === 'duplicate') {
            $filterId = (int) ($_POST['filter_id'] ?? 0);
            $existing = fetch_filters($pdo, $userId);
            $byId = index_by_id($existing);
            if ($filterId < 1 || !isset($byId[$filterId])) {
                flash('Filter not found', 'error');
                redirect();
            }
            $src = $byId[$filterId];
            $parent = (int) $src['tparent'];
            $torder = next_torder($pdo, $userId, $parent);
            $title = $src['title'] . ' (copy)';
            $stmt = $pdo->prepare(
                'INSERT INTO filters (userid, filtertype, title, icon, torder, tparent, tree, valuelist, sorton, sortorder, enablenotify)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId, 'filter', $title, $src['icon'], $torder, $parent,
                (string) $src['tree'], (string) ($src['valuelist'] ?? ''), (string) ($src['sorton'] ?? ''),
                (string) ($src['sortorder'] ?? ''), (int) $src['enablenotify'],
            ]);
            flash("Duplicated as '$title'");
            redirect(['highlight' => (int) $pdo->lastInsertId()]);
        }

        if (in_array($action, ['moveup', 'movedown'], true)) {
            $filterId = (int) ($_POST['filter_id'] ?? 0);
            $existing = fetch_filters($pdo, $userId);
            $byId = index_by_id($existing);
            if ($filterId < 1 || !isset($byId[$filterId])) {
                flash('Filter not found', 'error');
                redirect();
            }
            $current = $byId[$filterId];
            $parent = (int) $current['tparent'];
            $siblings = array_values(array_filter($existing, static function ($f) use ($parent) {
                return (int) $f['tparent'] === $parent;
            }));
            usort($siblings, static function ($a, $b) {
                $c = ((int) $a['torder']) <=> ((int) $b['torder']);
                return $c !== 0 ? $c : ((int) $a['id'] <=> (int) $b['id']);
            });
            $idx = null;
            foreach ($siblings as $i => $s) {
                if ((int) $s['id'] === $filterId) {
                    $idx = $i;
                    break;
                }
            }
            $swapWith = $action === 'moveup' ? $idx - 1 : $idx + 1;
            if ($idx === null || !isset($siblings[$swapWith])) {
                redirect(['highlight' => $filterId]);
            }
            $a = $siblings[$idx];
            $b = $siblings[$swapWith];
            $pdo->prepare('UPDATE filters SET torder = ? WHERE userid = ? AND id = ?')->execute([(int) $b['torder'], $userId, (int) $a['id']]);
            $pdo->prepare('UPDATE filters SET torder = ? WHERE userid = ? AND id = ?')->execute([(int) $a['torder'], $userId, (int) $b['id']]);
            flash('Filter reordered');
            redirect(['highlight' => $filterId]);
        }

        if ($action === 'cleanup') {
            $stmt = $pdo->prepare("DELETE FROM filters WHERE userid = ? AND filtertype = 'index_filter' AND title <> 'Index filter'");
            $stmt->execute([$userId]);
            $deleted = $stmt->rowCount();
            flash($deleted > 0 ? "Removed $deleted leftover index_filter row(s)" : 'Nothing to clean up');
            redirect();
        }

        if ($action === 'restore') {
            $srcId = 1;
            if ($userId === $srcId) {
                flash('Cannot restore defaults onto the anonymous user', 'error');
                redirect();
            }
            $n = copy_filters_from($pdo, $srcId, $userId);
            flash("Restored $n default filters from the anonymous user");
            redirect();
        }

        flash('Unknown action', 'error');
        redirect();
    } catch (Exception $e) {
        flash('Error: ' . $e->getMessage(), 'error');
        redirect();
    }
}

// ---------------------------------------------------------------------------
// View data
// ---------------------------------------------------------------------------
try {
    $filters = fetch_filters($pdo, $userId);
} catch (Exception $e) {
    $filters = [];
    flash('Could not load filters: ' . $e->getMessage(), 'error');
}
$flat = flatten_tree($filters);
$byId = index_by_id($filters);
$flash = take_flash();

$editing = null;
if ($editId && isset($byId[$editId])) {
    $editing = $byId[$editId];
    $parsed = parse_valuelist($editing['valuelist'] ?? '');
    $editing['_search_type'] = $parsed['search_type'];
    $editing['_search_text'] = $parsed['search_text'];
    $editing['_age'] = $parsed['age'];
    $tree = (string) ($editing['tree'] ?? '');
    $editing['_custom_tree'] = in_array($tree, $presetTrees, true) ? '' : $tree;
}

$formTitle = $editing['title'] ?? '';
$formSearchType = $editing['_search_type'] ?? 'Title';
$formSearchText = $editing['_search_text'] ?? '';
$formAge = $editing['_age'] ?? '';
$formCategory = $editing ? rtrim((string) $editing['tree'], ',') : '';
$formCustomTree = $editing['_custom_tree'] ?? '';
$formIcon = sanitize_icon((string) ($editing['icon'] ?? 'custom'), $allowedIcons);
$formSorton = (string) ($editing['sorton'] ?? 'stamp');
if ($formSorton === '') {
    $formSorton = 'stamp';
}
$formSortorder = strtoupper((string) ($editing['sortorder'] ?? default_sortorder($formSorton)));
if (!in_array($formSortorder, $allowedSortorder, true)) {
    $formSortorder = default_sortorder($formSorton);
}
$formParent = $editing ? (int) $editing['tparent'] : 0;
$formNotify = $editing ? (int) $editing['enablenotify'] : 0;

$brokenIndex = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM filters WHERE userid = ? AND filtertype = 'index_filter' AND title <> 'Index filter'");
    $stmt->execute([$userId]);
    $brokenIndex = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    $brokenIndex = 0;
}

$topLevel = 0;
foreach ($filters as $f) {
    if ((int) $f['tparent'] === 0) {
        $topLevel++;
    }
}

$parentChoices = [];
$skipIds = [];
if ($editing) {
    $skipIds = array_merge([(int) $editing['id']], descendant_ids($byId, (int) $editing['id']));
}
foreach ($flat as $f) {
    if (in_array((int) $f['id'], $skipIds, true)) {
        continue;
    }
    $parentChoices[] = $f;
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Spotweb Filter Manager</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #121225; color: #e0e0e0; padding: 20px; }
  .container { max-width: 1100px; margin: 0 auto; }
  h1 { color: #0fbcf9; margin-bottom: 4px; font-size: 24px; }
  .subtitle { color: #8b8ba0; font-size: 13px; }
  .card { background: #16213e; border-radius: 12px; padding: 22px; margin-bottom: 18px; border: 1px solid #1e2a4a; }
  .card h2 { color: #0fbcf9; margin-bottom: 14px; font-size: 17px; }
  label { display: block; margin-bottom: 4px; color: #aaa; font-size: 12px; font-weight: 600; letter-spacing: .02em; }
  input[type="text"], input[type="search"], select { width: 100%; padding: 9px 11px; border: 1px solid #2a2a4e; border-radius: 8px; background: #0d1117; color: #e0e0e0; font-size: 14px; margin-bottom: 12px; }
  input:focus, select:focus { outline: none; border-color: #0fbcf9; }
  .row { display: flex; gap: 12px; flex-wrap: wrap; }
  .row > div { flex: 1; min-width: 160px; }
  button, .btn { padding: 9px 16px; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; transition: background .15s, transform .05s; text-decoration: none; display: inline-block; }
  button:active { transform: translateY(1px); }
  .btn-primary { background: #0fbcf9; color: #0d1117; }
  .btn-primary:hover { background: #0ea5e9; }
  .btn-ghost { background: transparent; color: #8b8ba0; border: 1px solid #2a2a4e; }
  .btn-ghost:hover { color: #e0e0e0; border-color: #0fbcf9; }
  .btn-danger { background: #e74c3c; color: #fff; padding: 5px 10px; font-size: 12px; }
  .btn-danger:hover { background: #c0392b; }
  .btn-sm { padding: 5px 10px; font-size: 12px; background: #2a2a4e; color: #e0e0e0; }
  .btn-sm:hover { background: #3a3a5e; }
  .btn-sm:disabled { opacity: .35; cursor: default; }
  .message { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
  .message.success { background: #0d2818; border: 1px solid #1a6b3c; color: #2ecc71; }
  .message.error { background: #2d0d0d; border: 1px solid #6b1a1a; color: #e74c3c; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; padding: 8px 10px; color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #2a2a4e; }
  td { padding: 8px 10px; border-bottom: 1px solid #1a1a3e; font-size: 13px; vertical-align: middle; }
  tr:hover td { background: rgba(15, 188, 249, .05); }
  tr.highlight td { background: rgba(15, 188, 249, .12); }
  .actions { display: flex; gap: 4px; flex-wrap: wrap; }
  .topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 18px; flex-wrap: wrap; }
  .topbar-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
  .topbar a { color: #8b8ba0; text-decoration: none; font-size: 13px; }
  .topbar a:hover { color: #0fbcf9; }
  .hint { background: #0d1117; border-radius: 8px; padding: 12px 16px; margin-top: 14px; font-size: 13px; color: #8b8ba0; line-height: 1.55; }
  .hint code { background: #1a1a3e; padding: 1px 6px; border-radius: 4px; color: #0fbcf9; }
  .empty { text-align: center; padding: 36px 16px; color: #666; }
  .indent { display: inline-block; color: #3a3a5e; }
  .spoticon { background-image: url('../../templates/modern/img/iconsprite.png'); background-repeat: no-repeat; display: inline-block; width: 16px; height: 16px; vertical-align: -2px; margin-right: 6px; }
  .spoticon-application { background-position: 0 0; }
  .spoticon-bluray { background-position: 0 -16px; }
  .spoticon-book { background-position: 0 -32px; }
  .spoticon-controller { background-position: 0 -48px; }
  .spoticon-custom { background-position: 0 -64px; }
  .spoticon-divx { background-position: 0 -80px; }
  .spoticon-download { background-position: 0 -96px; }
  .spoticon-dvd { background-position: 0 -112px; }
  .spoticon-eye { background-position: 0 -128px; }
  .spoticon-fav { background-position: 0 -144px; }
  .spoticon-female { background-position: 0 -160px; }
  .spoticon-film { background-position: 0 -176px; }
  .spoticon-hd { background-position: 0 -192px; }
  .spoticon-help { background-position: 0 -208px; }
  .spoticon-home { background-position: 0 -224px; }
  .spoticon-ipod { background-position: 0 -240px; }
  .spoticon-linux { background-position: 0 -256px; }
  .spoticon-mac { background-position: 0 -272px; }
  .spoticon-mpg { background-position: 0 -288px; }
  .spoticon-music { background-position: 0 -304px; }
  .spoticon-nintendo_ds { background-position: 0 -320px; }
  .spoticon-nintendo_wii { background-position: 0 -336px; }
  .spoticon-pda { background-position: 0 -352px; }
  .spoticon-phone { background-position: 0 -368px; }
  .spoticon-picture { background-position: 0 -384px; }
  .spoticon-playstation { background-position: 0 -400px; }
  .spoticon-refresh { background-position: 0 -416px; }
  .spoticon-tag { background-position: 0 -432px; }
  .spoticon-today { background-position: 0 -448px; }
  .spoticon-tv { background-position: 0 -464px; }
  .spoticon-vista { background-position: 0 -480px; }
  .spoticon-windows { background-position: 0 -496px; }
  .spoticon-wmv { background-position: 0 -512px; }
  .spoticon-xbox { background-position: 0 -528px; }
  .spoticon-stats { background-position: 0 -544px; }
  .spoticon-config { background-position: 0 -560px; }
  .presets { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
  .chip { background: #0d1117; border: 1px solid #2a2a4e; color: #c8c8d8; border-radius: 999px; padding: 5px 10px; font-size: 12px; cursor: pointer; }
  .chip:hover { border-color: #0fbcf9; color: #0fbcf9; }
  .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
  .muted { color: #666; }
  .badge { display: inline-block; background: #1a1a3e; color: #8b8ba0; border-radius: 999px; padding: 1px 7px; font-size: 11px; margin-left: 6px; }
  .check { display: flex; align-items: center; gap: 8px; margin: 4px 0 12px; color: #ccc; font-size: 13px; }
  .check input { width: auto; margin: 0; }
  .table-wrap { overflow-x: auto; }
  .namecell { white-space: nowrap; }
  .cat-cat0 { box-shadow: inset 3px 0 0 #4a90d9; }
  .cat-cat1 { box-shadow: inset 3px 0 0 #9b59b6; }
  .cat-cat2 { box-shadow: inset 3px 0 0 #27ae60; }
  .cat-cat3 { box-shadow: inset 3px 0 0 #e67e22; }
  .advanced { margin-top: 4px; }
  details.advanced summary { cursor: pointer; color: #8b8ba0; font-size: 13px; margin-bottom: 8px; }
  .form-actions { display: flex; gap: 8px; align-items: center; margin-top: 4px; }
  @media (max-width: 720px) {
    body { padding: 12px; }
    .card { padding: 16px; }
    .hide-sm { display: none; }
  }
</style>
</head>
<body>
<div class="container">
  <div class="topbar">
    <div>
      <h1>Spotweb Filter Manager</h1>
      <div class="subtitle">
        Signed in as <strong><?php echo h($username); ?></strong>
        <?php if ($isAdmin && $userId !== $authUserId): ?>
          — editing <strong><?php echo h($targetName); ?></strong>
        <?php endif; ?>
        &mdash; sidebar filters for this account
      </div>
    </div>
    <div class="topbar-actions">
<?php if ($isAdmin && $allUsers): ?>
      <form method="post" action="filter-manager.php" style="display:flex;gap:6px;align-items:center;">
        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
        <input type="hidden" name="action" value="switch_user">
        <label for="target_userid" style="margin:0;white-space:nowrap;">User</label>
        <select name="target_userid" id="target_userid" style="margin:0;min-width:140px;" onchange="this.form.submit()">
<?php foreach ($allUsers as $u): ?>
          <option value="<?php echo (int) $u['id']; ?>" <?php echo (int) $u['id'] === $userId ? 'selected' : ''; ?>>
            <?php echo h($u['username']); ?><?php echo (int) $u['id'] === 1 ? ' (defaults)' : ''; ?>
          </option>
<?php endforeach; ?>
        </select>
      </form>
<?php endif; ?>
      <a href="<?php echo h(spotweb_base_url() . '/'); ?>">Open Spotweb</a>
      <a href="filter-manager.php?logout=1">Logout</a>
    </div>
  </div>

<?php if ($flash): ?>
  <div class="message <?php echo h($flash['type']); ?>" id="flash"><?php echo h($flash['message']); ?></div>
<?php endif; ?>

  <div class="card">
    <h2><?php echo $editing ? 'Edit filter' : '+ Add filter'; ?></h2>
<?php if (!$editing): ?>
    <div class="presets" id="presets">
      <button type="button" class="chip" data-preset="uhd">UHD movies</button>
      <button type="button" class="chip" data-preset="hd">HD movies</button>
      <button type="button" class="chip" data-preset="series">TV series</button>
      <button type="button" class="chip" data-preset="ebooks">eBooks</button>
      <button type="button" class="chip" data-preset="music">Music</button>
      <button type="button" class="chip" data-preset="pcgames">PC games</button>
      <button type="button" class="chip" data-preset="winapps">Windows apps</button>
    </div>
<?php endif; ?>
    <form method="post" action="filter-manager.php" id="filter-form">
      <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'add'; ?>">
<?php if ($editing): ?>
      <input type="hidden" name="filter_id" value="<?php echo (int) $editing['id']; ?>">
<?php endif; ?>

      <label for="title">Name (shown in the sidebar)</label>
      <input type="text" id="title" name="title" value="<?php echo h($formTitle); ?>" placeholder="e.g. UHD Movies, eBooks, PC Games" required minlength="2" maxlength="128">

      <div class="row">
        <div>
          <label for="search_type">Search field</label>
          <select id="search_type" name="search_type">
            <option value="Title" <?php echo $formSearchType === 'Title' ? 'selected' : ''; ?>>Title</option>
            <option value="Tag" <?php echo $formSearchType === 'Tag' ? 'selected' : ''; ?>>Tag</option>
            <option value="Poster" <?php echo $formSearchType === 'Poster' ? 'selected' : ''; ?>>Poster</option>
            <option value="SpotterID" <?php echo $formSearchType === 'SpotterID' ? 'selected' : ''; ?>>Spotter ID</option>
          </select>
        </div>
        <div>
          <label for="search_text">Search text (optional)</label>
          <input type="text" id="search_text" name="search_text" value="<?php echo h($formSearchText); ?>" placeholder="e.g. 4K, eBook, S01">
        </div>
        <div>
          <label for="age">Age</label>
          <select id="age" name="age">
<?php foreach ($ageOptions as $val => $label): ?>
            <option value="<?php echo h($val); ?>" <?php echo $formAge === $val ? 'selected' : ''; ?>><?php echo h($label); ?></option>
<?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row">
        <div>
          <label for="category">Category</label>
          <select id="category" name="category">
<?php
$categoryGroups = [
    'Image' => [
        'cat0_z0' => 'Movies',
        'cat0_z0_a15' => 'UHD / 4K movies',
        'cat0_z0_a4,cat0_z0_a6,cat0_z0_a7,cat0_z0_a8,cat0_z0_a9,cat0_z0_a15' => 'HD movies',
        'cat0_z0_a6' => 'Blu-ray',
        'cat0_z0_a0' => 'DivX / xvid',
        'cat0_z1' => 'Series',
        'cat0_z2' => 'Books',
        'cat0_z3' => 'Erotica',
        'cat0_z4' => 'Pictures',
        'cat0' => 'All image',
    ],
    'Audio' => [
        'cat1' => 'All audio',
        'cat1_a0,cat1_a3,cat1_a5,cat1_a6' => 'Compressed',
        'cat1_a2,cat1_a4,cat1_a7,cat1_a8' => 'Lossless',
    ],
    'Games' => [
        'cat2' => 'All games',
        'cat2_a0' => 'Windows',
        'cat2_a1,cat2_a2' => 'Mac / Linux',
        'cat2_a3,cat2_a4,cat2_a5,cat2_a12' => 'PlayStation',
        'cat2_a6,cat2_a7' => 'Xbox',
        'cat2_a8,cat2_a9,cat2_a10,cat2_a11' => 'Nintendo',
    ],
    'Applications' => [
        'cat3' => 'All applications',
        'cat3_a0' => 'Windows',
        'cat3_a1,cat3_a2,cat3_a3' => 'Mac / Linux',
        'cat3_a6' => 'iOS',
        'cat3_a7' => 'Android',
    ],
];
?>
            <option value="" <?php echo $formCategory === '' ? 'selected' : ''; ?>>All categories</option>
<?php foreach ($categoryGroups as $groupLabel => $opts): ?>
            <optgroup label="<?php echo h($groupLabel); ?>">
<?php foreach ($opts as $val => $lab): ?>
              <option value="<?php echo h($val); ?>" <?php echo $formCategory === $val ? 'selected' : ''; ?>><?php echo h($lab); ?></option>
<?php endforeach; ?>
            </optgroup>
<?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="tparent">Nest under</label>
          <select id="tparent" name="tparent">
            <option value="0">Top level (sidebar root)</option>
<?php foreach ($parentChoices as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>" <?php echo $formParent === (int) $p['id'] ? 'selected' : ''; ?>>
              <?php echo str_repeat('— ', (int) $p['_depth']); ?><?php echo h($p['title']); ?>
            </option>
<?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row">
        <div>
          <label for="icon">Icon</label>
          <div style="display:flex;gap:8px;align-items:center;">
            <span id="icon-preview" class="spoticon spoticon-<?php echo h($formIcon); ?>"></span>
            <select id="icon" name="icon" style="margin-bottom:12px;">
<?php foreach (filter_icons() as $key => $label): ?>
              <option value="<?php echo h($key); ?>" <?php echo $formIcon === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option>
<?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label for="sorton">Sort by</label>
          <select id="sorton" name="sorton">
            <option value="stamp" <?php echo $formSorton === 'stamp' ? 'selected' : ''; ?>>Date</option>
            <option value="title" <?php echo $formSorton === 'title' ? 'selected' : ''; ?>>Title</option>
            <option value="poster" <?php echo $formSorton === 'poster' ? 'selected' : ''; ?>>Poster</option>
            <option value="spotrating" <?php echo $formSorton === 'spotrating' ? 'selected' : ''; ?>>Rating</option>
            <option value="commentcount" <?php echo $formSorton === 'commentcount' ? 'selected' : ''; ?>>Comments</option>
          </select>
        </div>
        <div>
          <label for="sortorder">Order</label>
          <select id="sortorder" name="sortorder">
            <option value="DESC" <?php echo $formSortorder === 'DESC' ? 'selected' : ''; ?>>Newest / high first (DESC)</option>
            <option value="ASC" <?php echo $formSortorder === 'ASC' ? 'selected' : ''; ?>>Oldest / A–Z (ASC)</option>
          </select>
        </div>
      </div>

      <label class="check"><input type="checkbox" name="enablenotify" value="1" <?php echo $formNotify ? 'checked' : ''; ?>> Notify me when this filter has new spots</label>

      <details class="advanced" <?php echo $formCustomTree !== '' ? 'open' : ''; ?>>
        <summary>Advanced tree</summary>
        <label for="custom_tree">Custom category tree (overrides the Category dropdown when filled)</label>
        <input type="text" id="custom_tree" name="custom_tree" value="<?php echo h($formCustomTree); ?>" placeholder="e.g. cat0_z0_a15 or cat0_z1,cat0_z0">
      </details>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Save changes' : 'Add filter'; ?></button>
<?php if ($editing): ?>
        <a class="btn btn-ghost" href="filter-manager.php">Cancel</a>
<?php endif; ?>
      </div>
    </form>

    <div class="hint">
      Filters show as a tree in Spotweb’s sidebar. Nesting puts a filter under another (for example HD under Image).
      The category controls <em>what spots match</em>, not where the item sits in the sidebar.
      Spotweb’s built-in “Image” filter is actually <code>cat0_z0</code> (movies).
    </div>
  </div>

  <div class="card">
    <div class="toolbar">
      <h2 style="margin:0;">Filters (<?php echo count($filters); ?><span class="muted">, <?php echo $topLevel; ?> top-level</span>)</h2>
      <div class="actions">
        <input type="search" id="list-search" placeholder="Filter this list…" style="margin:0;min-width:180px;">
<?php if ($brokenIndex > 0): ?>
        <form method="post" action="filter-manager.php" onsubmit="return confirm('Remove leftover index_filter rows that are not the real index filter?');">
          <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
          <input type="hidden" name="action" value="cleanup">
          <button type="submit" class="btn-sm" title="Remove broken index_filter leftovers">Clean leftovers (<?php echo $brokenIndex; ?>)</button>
        </form>
<?php endif; ?>
<?php if ($userId !== 1): ?>
        <form method="post" action="filter-manager.php" onsubmit="return confirm('Replace ALL filters for this user with the anonymous/default set? This cannot be undone.');">
          <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
          <input type="hidden" name="action" value="restore">
          <button type="submit" class="btn-sm" title="Copy filters from the anonymous user">Restore defaults</button>
        </form>
<?php endif; ?>
      </div>
    </div>
<?php if (empty($flat)): ?>
    <div class="empty">No filters yet. Add one above, or restore the Spotweb defaults.</div>
<?php else: ?>
    <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Id</th>
          <th>Name</th>
          <th>Category</th>
          <th class="hide-sm">Search</th>
          <th class="hide-sm">Sort</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($flat as $f):
    $catClass = '';
    $tree = (string) ($f['tree'] ?? '');
    if (str_contains($tree, 'cat0')) {
        $catClass = 'cat-cat0';
    } elseif (str_contains($tree, 'cat1')) {
        $catClass = 'cat-cat1';
    } elseif (str_contains($tree, 'cat2')) {
        $catClass = 'cat-cat2';
    } elseif (str_contains($tree, 'cat3')) {
        $catClass = 'cat-cat3';
    }
    $icon = sanitize_icon((string) $f['icon'], $allowedIcons);
    $searchLabel = valuelist_to_text($f['valuelist'] ?? '');
    $isFirst = ((int) $f['_index'] === 0);
    $isLast = ((int) $f['_index'] === (int) $f['_count'] - 1);
    $hl = $highlightId === (int) $f['id'] ? ' highlight' : '';
    $preview = filter_preview_url($f);
    $childNote = ((int) $f['_childcount'] > 0) ? (int) $f['_childcount'] : 0;
    $delMsg = $childNote
        ? "Delete '{$f['title']}' and {$childNote} nested filter(s)?"
        : "Delete filter '{$f['title']}'?";
    $sortLabel = trim((string) ($f['sorton'] ?? ''));
    if ($sortLabel !== '') {
        $sortLabel .= ' ' . strtolower((string) ($f['sortorder'] ?: default_sortorder($sortLabel)));
    }
?>
        <tr class="<?php echo h($catClass . $hl); ?>" data-name="<?php echo h(function_exists('mb_strtolower') ? mb_strtolower($f['title']) : strtolower($f['title'])); ?>" id="f-<?php echo (int) $f['id']; ?>">
          <td class="muted"><?php echo (int) $f['id']; ?></td>
          <td class="namecell">
            <span class="indent"><?php echo str_repeat('&nbsp;&nbsp;&nbsp;', (int) $f['_depth']); ?></span>
            <span class="spoticon spoticon-<?php echo h($icon); ?>"></span>
            <strong><?php echo h($f['title']); ?></strong>
            <?php if ($childNote): ?><span class="badge"><?php echo $childNote; ?></span><?php endif; ?>
          </td>
          <td><?php echo h(tree_to_name($f['tree'] ?? '')); ?></td>
          <td class="hide-sm"><?php echo $searchLabel !== '' ? h($searchLabel) : '<span class="muted">—</span>'; ?></td>
          <td class="hide-sm"><?php echo $sortLabel !== '' ? h($sortLabel) : '<span class="muted">—</span>'; ?></td>
          <td class="actions">
            <form method="post" action="filter-manager.php">
              <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
              <input type="hidden" name="action" value="moveup">
              <input type="hidden" name="filter_id" value="<?php echo (int) $f['id']; ?>">
              <button type="submit" class="btn-sm" title="Move up" <?php echo $isFirst ? 'disabled' : ''; ?>>&uarr;</button>
            </form>
            <form method="post" action="filter-manager.php">
              <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
              <input type="hidden" name="action" value="movedown">
              <input type="hidden" name="filter_id" value="<?php echo (int) $f['id']; ?>">
              <button type="submit" class="btn-sm" title="Move down" <?php echo $isLast ? 'disabled' : ''; ?>>&darr;</button>
            </form>
            <a class="btn-sm" href="filter-manager.php?edit=<?php echo (int) $f['id']; ?>">Edit</a>
            <form method="post" action="filter-manager.php">
              <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
              <input type="hidden" name="action" value="duplicate">
              <input type="hidden" name="filter_id" value="<?php echo (int) $f['id']; ?>">
              <button type="submit" class="btn-sm" title="Duplicate">Copy</button>
            </form>
            <a class="btn-sm" href="<?php echo h($preview); ?>" target="_blank" rel="noopener" title="Open this filter in Spotweb">Open</a>
            <form method="post" action="filter-manager.php" onsubmit="return confirm(<?php echo js_str($delMsg); ?>);">
              <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="filter_id" value="<?php echo (int) $f['id']; ?>">
              <button type="submit" class="btn-danger" title="Delete">Delete</button>
            </form>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    </div>
<?php endif; ?>
  </div>

  <div class="hint">
    Stored in the <code>filters</code> table for user <strong><?php echo h($targetName); ?></strong> (id <?php echo (int) $userId; ?>).
    Changes show up in the Spotweb sidebar on the next page load.
  </div>
</div>
<script>
(function () {
  var category = document.getElementById('category');
  if (category) {
    var wanted = <?php echo js_str($formCategory); ?>;
    if (wanted) {
      var found = false;
      for (var i = 0; i < category.options.length; i++) {
        if (category.options[i].value === wanted) { found = true; break; }
      }
      category.value = found ? wanted : '';
    }
  }

  var iconSel = document.getElementById('icon');
  var preview = document.getElementById('icon-preview');
  if (iconSel && preview) {
    iconSel.addEventListener('change', function () {
      preview.className = 'spoticon spoticon-' + iconSel.value;
    });
  }

  var sorton = document.getElementById('sorton');
  var sortorder = document.getElementById('sortorder');
  var sortTouched = false;
  if (sortorder) {
    sortorder.addEventListener('change', function () { sortTouched = true; });
  }
  if (sorton && sortorder) {
    sorton.addEventListener('change', function () {
      if (sortTouched) return;
      sortorder.value = (sorton.value === 'title' || sorton.value === 'poster') ? 'ASC' : 'DESC';
    });
  }

  var search = document.getElementById('list-search');
  if (search) {
    search.addEventListener('input', function () {
      var q = search.value.toLowerCase().trim();
      document.querySelectorAll('tbody tr[data-name]').forEach(function (row) {
        row.style.display = (!q || row.getAttribute('data-name').indexOf(q) !== -1) ? '' : 'none';
      });
    });
  }

  var flash = document.getElementById('flash');
  if (flash) {
    setTimeout(function () { flash.style.opacity = '0'; flash.style.transition = 'opacity .4s'; }, 4500);
  }

  var hl = document.querySelector('tr.highlight');
  if (hl) { hl.scrollIntoView({ block: 'center' }); }

  var presets = {
    uhd:     { title: 'UHD', category: 'cat0_z0_a15', icon: 'hd', search_text: '', search_type: 'Title', sorton: 'stamp' },
    hd:      { title: 'HD', category: 'cat0_z0_a4,cat0_z0_a6,cat0_z0_a7,cat0_z0_a8,cat0_z0_a9,cat0_z0_a15', icon: 'hd', search_text: '', search_type: 'Title', sorton: 'stamp' },
    series:  { title: 'Series', category: 'cat0_z1', icon: 'tv', search_text: '', search_type: 'Title', sorton: 'stamp' },
    ebooks:  { title: 'eBooks', category: 'cat0_z2', icon: 'book', search_text: 'eBook', search_type: 'Title', sorton: 'stamp' },
    music:   { title: 'Music', category: 'cat1', icon: 'music', search_text: '', search_type: 'Title', sorton: 'stamp' },
    pcgames: { title: 'PC Games', category: 'cat2_a0', icon: 'windows', search_text: '', search_type: 'Title', sorton: 'stamp' },
    winapps: { title: 'Windows Apps', category: 'cat3_a0', icon: 'vista', search_text: '', search_type: 'Title', sorton: 'stamp' }
  };
  document.querySelectorAll('#presets .chip').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var p = presets[btn.getAttribute('data-preset')];
      if (!p) return;
      document.getElementById('title').value = p.title;
      document.getElementById('category').value = p.category;
      document.getElementById('icon').value = p.icon;
      document.getElementById('icon').dispatchEvent(new Event('change'));
      document.getElementById('search_text').value = p.search_text;
      document.getElementById('search_type').value = p.search_type;
      document.getElementById('sorton').value = p.sorton;
      document.getElementById('sortorder').value = 'DESC';
      document.getElementById('title').focus();
    });
  });
})();
</script>
</body>
</html>
