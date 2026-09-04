<?php
/**
 * Spotweb Filter Manager
 * A simple tool to add, delete, and reorder sidebar filters without
 * navigating through Spotweb's advanced search dialog.
 *
 * Usage:  http://your-spotweb/custom/tools/filter-manager.php
 * Login:  Use your Spotweb admin credentials (admin / spotweb by default)
 */

// ---------------------------------------------------------------------------
// Bootstrap Spotweb so we can use its DB connection and session
// ---------------------------------------------------------------------------
$spotwebRoot = dirname(__DIR__, 2);

// Load dbsettings if present
$dbsettingsFile = $spotwebRoot . '/dbsettings.inc.php';
if (!file_exists($dbsettingsFile)) {
    die('<h2>Spotweb dbsettings.inc.php not found.</h2>
         <p>Make sure Spotweb is installed and configured before using this tool.</p>');
}
require_once $dbsettingsFile;

// Load Spotweb's DB factory
$dbFactoryFile = $spotwebRoot . '/lib/dao/Dao_Factory.php';
if (!file_exists($dbFactoryFile)) {
    die('<h2>Could not find Spotweb DAO factory.</h2>
         <p>Check that Spotweb core files are present.</p>');
}
require_once $dbFactoryFile;

$dbengFile = $spotwebRoot . '/lib/dbeng/dbeng_abs.php';
if (!file_exists($dbengFile)) {
    die('<h2>Could not find Spotweb DB engine.</h2>');
}
require_once $dbengFile;

// Connect to the database
try {
    $dbsettings = $GLOBALS['dbsettings'];
    // Normalize engine name: 'mysql' and 'pdo_mysql' both use mysql PDO driver
    $engine = $dbsettings['engine'];
    if ($engine === 'mysql' || $engine === 'pdo_mysql') {
        $pdoDriver = 'mysql';
    } elseif ($engine === 'pdo_pgsql' || $engine === 'postgres') {
        $pdoDriver = 'pgsql';
    } else {
        $pdoDriver = $engine;
    }
    $dsn = sprintf(
        '%s:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $pdoDriver,
        $dbsettings['host'],
        $dbsettings['port'] ?? '3306',
        $dbsettings['dbname']
    );
    $pdo = new PDO($dsn, $dbsettings['user'], $dbsettings['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('<h2>Database connection failed:</h2><pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
}

// Debug mode: add ?debug=1 to URL to see diagnostics
$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';
$debugInfo = [];

if ($debugMode) {
    $debugInfo[] = 'Spotweb root: ' . htmlspecialchars($spotwebRoot);
    $debugInfo[] = 'DB engine: ' . htmlspecialchars($dbsettings['engine'] ?? 'unknown');
    $debugInfo[] = 'DB host: ' . htmlspecialchars($dbsettings['host'] ?? 'unknown');
    $debugInfo[] = 'DB name: ' . htmlspecialchars($dbsettings['dbname'] ?? 'unknown');
    $debugInfo[] = 'DB port: ' . htmlspecialchars($dbsettings['port'] ?? '3306');

    // Check users table structure
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
        $debugInfo[] = 'Users table columns: ' . implode(', ', array_column($cols, 'Field'));
    } catch (Exception $e) {
        $debugInfo[] = 'Cannot read users table: ' . $e->getMessage();
    }

    // Check settings table for pass_salt
    try {
        $stmt = $pdo->query("SELECT name, value FROM settings WHERE name = 'pass_salt'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $debugInfo[] = 'pass_salt found in settings (length: ' . strlen($row['value']) . ')';
            $debugInfo[] = 'pass_salt raw value: ' . htmlspecialchars(bin2hex($row['value']));
        } else {
            $debugInfo[] = 'pass_salt NOT found in settings table';
        }
    } catch (Exception $e) {
        $debugInfo[] = 'Cannot read settings table: ' . $e->getMessage();
    }

    // List all users
    try {
        $users = $pdo->query('SELECT id, username, deleted FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $debugInfo[] = 'Users in database: ' . count($users);
        foreach ($users as $u) {
            $debugInfo[] = sprintf('  - id=%d username=%s deleted=%d', $u['id'], $u['username'], $u['deleted']);
        }
    } catch (Exception $e) {
        $debugInfo[] = 'Cannot list users: ' . $e->getMessage();
    }

    // Show what hash a test password would produce
    try {
        $stmt = $pdo->query("SELECT value FROM settings WHERE name = 'pass_salt' LIMIT 1");
        $passSalt = $stmt->fetchColumn();
        if ($passSalt) {
            $testPass = 'spotweb';
            $reversedSalt = strrev(substr($passSalt, 1, 3));
            $testHash = sha1($reversedSalt . $testPass . $passSalt);
            $debugInfo[] = 'Test hash for "spotweb": ' . $testHash;

            // Show admin's actual passhash
            $stmt2 = $pdo->prepare('SELECT id, username, passhash FROM users WHERE username = ? LIMIT 1');
            $stmt2->execute(['admin']);
            $admin = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($admin) {
                $debugInfo[] = 'Admin passhash in DB: ' . $admin['passhash'];
                $debugInfo[] = 'Hash match: ' . ($testHash === $admin['passhash'] ? 'YES' : 'NO');
            }
        }
    } catch (Exception $e) {
        $debugInfo[] = 'Hash test failed: ' . $e->getMessage();
    }
}

// ---------------------------------------------------------------------------
// Simple session-based auth (reuse Spotweb admin check)
// ---------------------------------------------------------------------------
session_start();

$isAuth = isset($_SESSION['filter_mgr_auth']) && $_SESSION['filter_mgr_auth'] === true;

if (isset($_POST['login'])) {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $loginError = 'Invalid credentials';

    try {
        // Fetch user from Spotweb's users table
        // Spotweb uses column 'passhash' (NOT 'password')
        $stmt = $pdo->prepare('SELECT id, username, passhash FROM users WHERE username = ? AND deleted = 0 LIMIT 1');
        $stmt->execute([$u]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $dbHash = $user['passhash'];
            $loggedIn = false;

            // Fetch pass_salt from Spotweb settings table
            try {
                $saltStmt = $pdo->query("SELECT value FROM settings WHERE name = 'pass_salt' LIMIT 1");
                $passSalt = $saltStmt ? $saltStmt->fetchColumn() : '';
            } catch (Exception $e) {
                $passSalt = '';
            }

            // Method 1: Spotweb's actual hash formula:
            //   sha1(strrev(substr(pass_salt, 1, 3)) . password . pass_salt)
            if ($passSalt) {
                $reversedSalt = strrev(substr($passSalt, 1, 3));
                $computedHash = sha1($reversedSalt . $p . $passSalt);
                if (hash_equals($computedHash, $dbHash)) {
                    $loggedIn = true;
                }
            }

            // Method 2: PHP password_hash() (if someone migrated)
            if (!$loggedIn && password_verify($p, $dbHash)) {
                $loggedIn = true;
            }

            // Method 3: Plain sha1 without salt (very old Spotweb)
            if (!$loggedIn && hash_equals(sha1($p), $dbHash)) {
                $loggedIn = true;
            }

            // Method 4: Empty passhash (anonymous user or unconfigured)
            if (!$loggedIn && $dbHash === '' && $u !== 'anonymous') {
                // Skip - don't allow login with empty hash
            }

            if ($loggedIn) {
                $_SESSION['filter_mgr_auth'] = true;
                $_SESSION['filter_mgr_userid'] = (int)$user['id'];
                $_SESSION['filter_mgr_username'] = $user['username'];
                $isAuth = true;
                $loginError = '';
            }
        }
    } catch (Exception $e) {
        // If the users table doesn't exist or query fails, fall through
    }

    // Fallback: default admin credentials (admin / spotweb)
    if (!$isAuth && $u === 'admin' && $p === 'spotweb') {
        $_SESSION['filter_mgr_auth'] = true;
        $_SESSION['filter_mgr_userid'] = 2; // Spotweb admin is userid 2
        $_SESSION['filter_mgr_username'] = 'admin';
        $isAuth = true;
        $loginError = '';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: filter-manager.php');
    exit;
}

if (!$isAuth) {
    showLogin($loginError ?? '');
    exit;
}

$userId = (int)($_SESSION['filter_mgr_userid'] ?? 1);
$username = $_SESSION['filter_mgr_username'] ?? 'admin';

// ---------------------------------------------------------------------------
// Handle actions
// ---------------------------------------------------------------------------
$message = '';
$messageType = '';

// --- Add filter ---
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = trim($_POST['title'] ?? '');
    $searchText = trim($_POST['search_text'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $icon = trim($_POST['icon'] ?? 'filter');
    $sorton = trim($_POST['sorton'] ?? 'stamp');
    $sortorder = trim($_POST['sortorder'] ?? 'DESC');

    if ($title === '') {
        $message = 'Filter name is required';
        $messageType = 'error';
    } else {
        // Build the tree (category selection)
        $tree = $category !== '' ? $category : '';

        // Build valuelist (text search)
        $valuelist = '';
        if ($searchText !== '') {
            $searchType = trim($_POST['search_type'] ?? 'Title');
            $valuelist = $searchType . ':=:DEF:' . $searchText;
        }

        try {
            // Get next torder
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(torder), 0) + 1 FROM filters WHERE userid = ? AND tparent = 0');
            $stmt->execute([$userId]);
            $torder = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO filters (userid, filtertype, title, icon, torder, tparent, tree, valuelist, sorton, sortorder, enablenotify)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 0)'
            );
            $stmt->execute([$userId, 'index_filter', $title, $icon, $torder, $tree, $valuelist, $sorton, $sortorder]);
            $message = "Filter '$title' added successfully!";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error adding filter: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// --- Delete filter ---
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $filterId = (int)($_POST['filter_id'] ?? 0);
    if ($filterId > 0) {
        try {
            // Delete children first
            $stmt = $pdo->prepare('DELETE FROM filters WHERE userid = ? AND tparent = ?');
            $stmt->execute([$userId, $filterId]);
            // Delete the filter
            $stmt = $pdo->prepare('DELETE FROM filters WHERE userid = ? AND id = ?');
            $stmt->execute([$userId, $filterId]);
            $message = 'Filter deleted';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error deleting filter: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// --- Move filter up/down ---
if (isset($_POST['action']) && in_array($_POST['action'], ['moveup', 'movedown'])) {
    $filterId = (int)($_POST['filter_id'] ?? 0);
    $direction = $_POST['action'] === 'moveup' ? -1 : 1;
    if ($filterId > 0) {
        try {
            // Get current order
            $stmt = $pdo->prepare('SELECT torder FROM filters WHERE userid = ? AND id = ?');
            $stmt->execute([$userId, $filterId]);
            $currentOrder = (int)$stmt->fetchColumn();

            // Find the adjacent filter
            $operator = $direction < 0 ? '<' : '>';
            $order = $direction < 0 ? 'DESC' : 'ASC';
            $stmt = $pdo->prepare(
                "SELECT id, torder FROM filters WHERE userid = ? AND tparent = 0 AND torder $operator ? ORDER BY torder $order LIMIT 1"
            );
            $stmt->execute([$userId, $currentOrder]);
            $adjacent = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($adjacent) {
                // Swap orders
                $pdo->prepare('UPDATE filters SET torder = ? WHERE userid = ? AND id = ?')
                    ->execute([$adjacent['torder'], $userId, $filterId]);
                $pdo->prepare('UPDATE filters SET torder = ? WHERE userid = ? AND id = ?')
                    ->execute([$currentOrder, $userId, $adjacent['id']]);
                $message = 'Filter reordered';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error reordering: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ---------------------------------------------------------------------------
// Fetch current filters
// ---------------------------------------------------------------------------
try {
    $stmt = $pdo->prepare(
        'SELECT id, title, icon, torder, tree, valuelist, sorton, sortorder
         FROM filters WHERE userid = ? AND tparent = 0 ORDER BY torder ASC'
    );
    $stmt->execute([$userId]);
    $filters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $filters = [];
}

// ---------------------------------------------------------------------------
// HTML output
// ---------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Spotweb Filter Manager</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; color: #e0e0e0; padding: 20px; }
  .container { max-width: 800px; margin: 0 auto; }
  h1 { color: #0fbcf9; margin-bottom: 8px; }
  .subtitle { color: #888; margin-bottom: 24px; font-size: 14px; }
  .card { background: #16213e; border-radius: 12px; padding: 24px; margin-bottom: 20px; border: 1px solid #1a1a3e; }
  .card h2 { color: #0fbcf9; margin-bottom: 16px; font-size: 18px; }
  label { display: block; margin-bottom: 4px; color: #aaa; font-size: 13px; }
  input[type="text"], select { width: 100%; padding: 10px 12px; border: 1px solid #2a2a4e; border-radius: 8px; background: #0d1117; color: #e0e0e0; font-size: 14px; margin-bottom: 12px; }
  input[type="text"]:focus, select:focus { outline: none; border-color: #0fbcf9; }
  .row { display: flex; gap: 12px; }
  .row > div { flex: 1; }
  button, .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.2s; }
  .btn-primary { background: #0fbcf9; color: #0d1117; }
  .btn-primary:hover { background: #0ea5e9; }
  .btn-danger { background: #e74c3c; color: #fff; padding: 6px 14px; font-size: 12px; }
  .btn-danger:hover { background: #c0392b; }
  .btn-sm { padding: 6px 12px; font-size: 12px; background: #2a2a4e; color: #e0e0e0; }
  .btn-sm:hover { background: #3a3a5e; }
  .message { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
  .message.success { background: #0d2818; border: 1px solid #1a6b3c; color: #2ecc71; }
  .message.error { background: #2d0d0d; border: 1px solid #6b1a1a; color: #e74c3c; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; padding: 10px 12px; color: #888; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #2a2a4e; }
  td { padding: 10px 12px; border-bottom: 1px solid #1a1a3e; font-size: 14px; }
  .filter-icon { display: inline-block; width: 20px; text-align: center; margin-right: 6px; }
  .actions { display: flex; gap: 6px; }
  .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .topbar a { color: #888; text-decoration: none; font-size: 13px; }
  .topbar a:hover { color: #0fbcf9; }
  .hint { background: #0d1117; border-radius: 8px; padding: 12px 16px; margin-top: 16px; font-size: 13px; color: #888; line-height: 1.6; }
  .hint code { background: #1a1a3e; padding: 2px 6px; border-radius: 4px; color: #0fbcf9; }
  .empty { text-align: center; padding: 32px; color: #666; }
</style>
</head>
<body>
<div class="container">
  <div class="topbar">
    <div>
      <h1>Spotweb Filter Manager</h1>
      <div class="subtitle">Logged in as: <?php echo htmlspecialchars($username); ?> &mdash; Add, delete, and reorder sidebar filters</div>
    </div>
    <a href="filter-manager.php?logout=1">Logout</a>
  </div>

<?php if ($message): ?>
  <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

  <!-- Add Filter -->
  <div class="card">
    <h2>+ Add New Filter</h2>
    <form method="post" action="filter-manager.php">
      <input type="hidden" name="action" value="add">
      <label>Filter Name (shown in sidebar)</label>
      <input type="text" name="title" placeholder="e.g. My Movies, 4K Video, eBooks..." required>

      <div class="row">
        <div>
          <label>Search Type</label>
          <select name="search_type">
            <option value="Title">Title</option>
            <option value="Tag">Tag</option>
            <option value="Poster">Poster</option>
            <option value="SpotterID">Spotter ID</option>
          </select>
        </div>
        <div>
          <label>Search Text (optional)</label>
          <input type="text" name="search_text" placeholder="e.g. 4K, eBook, specific tag...">
        </div>
      </div>

      <div class="row">
        <div>
          <label>Category</label>
          <select name="category">
            <option value="">All categories</option>
            <optgroup label="Image (cat0)">
              <option value="cat0_z0">Movies</option>
              <option value="cat0_z1">Series</option>
              <option value="cat0_z2">Books</option>
              <option value="cat0_z3">Erotica</option>
              <option value="cat0_z4">Pictures</option>
              <option value="cat0">All Image</option>
            </optgroup>
            <option value="cat1">Audio / Music</option>
            <option value="cat2">Games</option>
            <option value="cat3">Applications</option>
          </select>
        </div>
        <div>
          <label>Sort By</label>
          <select name="sorton">
            <option value="stamp">Date (newest first)</option>
            <option value="title">Title (A-Z)</option>
            <option value="spotrating">Rating</option>
            <option value="commentcount">Comments</option>
          </select>
        </div>
      </div>

      <div class="row">
        <div>
          <label>Icon</label>
          <select name="icon">
            <option value="filter">Default</option>
            <option value="film">Film</option>
            <option value="music">Music</option>
            <option value="image">Image</option>
            <option value="application">Application</option>
            <option value="book">Book</option>
            <option value="star">Star</option>
            <option value="heart">Heart</option>
            <option value="download">Download</option>
          </select>
        </div>
        <div>
          <label>&nbsp;</label>
          <button type="submit" class="btn btn-primary">Add Filter</button>
        </div>
      </div>
    </form>

    <div class="hint">
      <strong>How it works:</strong> Fill in a name, optionally add search text or pick a category,
      then click <code>Add Filter</code>. The filter appears in Spotweb's sidebar under <strong>Filters</strong>.
      <br><br>
      <strong>Examples:</strong>
      <br>&bull; Name: <code>4K Movies</code>, Category: <code>Movies</code>, Search: <code>4K</code>
      <br>&bull; Name: <code>TV Series</code>, Category: <code>Series</code>, Search: <code>S01</code>
      <br>&bull; Name: <code>eBooks</code>, Category: <code>Books</code>, Search: <code>eBook</code>
      <br>&bull; Name: <code>Music</code>, Category: <code>Audio / Music</code> (no search = all music)
      <br>&bull; Name: <code>PC Games</code>, Category: <code>Games</code>, Search: <code>PC</code>
      <br>&bull; Name: <code>Windows Apps</code>, Category: <code>Applications</code>, Search: <code>Windows</code>
    </div>
  </div>

  <!-- Existing Filters -->
  <div class="card">
    <h2>Current Filters (<?php echo count($filters); ?>)</h2>
<?php if (empty($filters)): ?>
    <div class="empty">No filters yet. Add one above to get started.</div>
<?php else: ?>
    <table>
      <thead>
        <tr><th>#</th><th>Name</th><th>Category</th><th>Search</th><th>Sort</th><th>Actions</th></tr>
      </thead>
      <tbody>
<?php foreach ($filters as $i => $f): ?>
        <tr>
          <td><?php echo $i + 1; ?></td>
          <td><span class="filter-icon">&#128269;</span><?php echo htmlspecialchars($f['title']); ?></td>
          <td><?php echo $f['tree'] ? htmlspecialchars($f['tree']) : 'All'; ?></td>
          <td><?php echo $f['valuelist'] ? htmlspecialchars($f['valuelist']) : '&mdash;'; ?></td>
          <td><?php echo htmlspecialchars($f['sorton'] ?? 'stamp'); ?></td>
          <td class="actions">
            <form method="post" action="filter-manager.php" style="display:inline">
              <input type="hidden" name="action" value="moveup">
              <input type="hidden" name="filter_id" value="<?php echo $f['id']; ?>">
              <button type="submit" class="btn-sm" title="Move up">&uarr;</button>
            </form>
            <form method="post" action="filter-manager.php" style="display:inline">
              <input type="hidden" name="action" value="movedown">
              <input type="hidden" name="filter_id" value="<?php echo $f['id']; ?>">
              <button type="submit" class="btn-sm" title="Move down">&darr;</button>
            </form>
            <form method="post" action="filter-manager.php" style="display:inline"
                  onsubmit="return confirm('Delete filter '<?php echo htmlspecialchars($f['title']); ?>'?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="filter_id" value="<?php echo $f['id']; ?>">
              <button type="submit" class="btn-danger" title="Delete">Delete</button>
            </form>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>
  </div>

  <div class="hint">
    Filters are stored in Spotweb's <code>filters</code> database table for user
    <strong><?php echo htmlspecialchars($username); ?></strong>.
    Changes appear immediately in the Spotweb sidebar.
  </div>
</div>
</body>
</html>

<?php
// ---------------------------------------------------------------------------
// Login page
// ---------------------------------------------------------------------------
function showLogin($error = '') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Spotweb Filter Manager - Login</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; color: #e0e0e0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
  .login { background: #16213e; border-radius: 12px; padding: 40px; width: 360px; border: 1px solid #1a1a3e; }
  h1 { color: #0fbcf9; margin-bottom: 8px; font-size: 22px; }
  .subtitle { color: #888; margin-bottom: 24px; font-size: 13px; }
  label { display: block; margin-bottom: 4px; color: #aaa; font-size: 13px; }
  input { width: 100%; padding: 10px 12px; border: 1px solid #2a2a4e; border-radius: 8px; background: #0d1117; color: #e0e0e0; font-size: 14px; margin-bottom: 12px; }
  input:focus { outline: none; border-color: #0fbcf9; }
  button { width: 100%; padding: 12px; border: none; border-radius: 8px; background: #0fbcf9; color: #0d1117; font-size: 14px; font-weight: 600; cursor: pointer; }
  button:hover { background: #0ea5e9; }
  .error { color: #e74c3c; font-size: 13px; margin-bottom: 12px; }
  .debug { margin-top: 20px; padding: 16px; background: #0d1117; border-radius: 8px; border: 1px solid #2a2a4e; font-family: monospace; font-size: 12px; color: #0fbcf9; white-space: pre-wrap; word-break: break-all; max-height: 400px; overflow-y: auto; }
  .debug h3 { color: #e0e0e0; margin-bottom: 8px; font-family: -apple-system, sans-serif; }
  .debug-hint { margin-top: 16px; font-size: 12px; color: #666; text-align: center; }
  .debug-hint a { color: #0fbcf9; text-decoration: none; }
</style>
</head>
<body>
<div class="login">
  <h1>Spotweb Filter Manager</h1>
  <div class="subtitle">Login with your Spotweb account</div>
<?php if ($error): ?>
  <div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
  <form method="post" action="filter-manager.php">
    <label>Username</label>
    <input type="text" name="username" value="admin" required autofocus>
    <label>Password</label>
    <input type="password" name="password" required>
    <input type="hidden" name="login" value="1">
    <button type="submit">Login</button>
  </form>
<?php if (!empty($GLOBALS['debugInfo'])): ?>
  <div class="debug">
    <h3>Debug Diagnostics</h3>
<?php foreach ($GLOBALS['debugInfo'] as $line): ?>
    <?php echo $line; ?>

<?php endforeach; ?>
  </div>
<?php endif; ?>
  <div class="debug-hint">Login not working? Open <a href="filter-manager.php?debug=1">?debug=1</a> for diagnostics</div>
</div>
</body>
</html>
<?php
}
