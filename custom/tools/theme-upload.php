<?php
/**
 * Spotweb Custom Theme Uploader
 * Allows users to upload custom CSS themes generated from the Theme Customizer
 */

// Paths (now in /custom/ folder structure)
define('THEME_DIR', __DIR__ . '/../themes');  // custom/themes/
define('RESERVED_THEMES', ['dark', 'midnight-ocean', 'cyberpunk', 'nord', 'dracula', 'forest', 'sunset', 'spring', 'summer', 'autumn', 'winter']);

$dbFile = dirname(__DIR__, 2) . '/dbsettings.inc.php';
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

function verify_admin_login(PDO $pdo, string $username, string $password): ?string
{
    $stmt = $pdo->prepare("SELECT id, username, passhash FROM users WHERE username = ? AND deleted = '0' LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || (string) $user['passhash'] === '') {
        return null;
    }
    $userId = (int) $user['id'];
    $isAdmin = $userId === 2;
    if (!$isAdmin) {
        $stmt = $pdo->prepare("SELECT 1 FROM usergroups ug INNER JOIN securitygroups sg ON sg.id = ug.groupid WHERE ug.userid = ? AND sg.name = 'Administrators'");
        $stmt->execute([$userId]);
        $isAdmin = (bool) $stmt->fetchColumn();
    }
    if (!$isAdmin) {
        return null;
    }
    $salt = '';
    $row = $pdo->query("SELECT value, serialized FROM settings WHERE name = 'pass_salt' LIMIT 1")->fetch();
    if ($row) {
        $salt = (string) $row['value'];
        if (!empty($row['serialized'])) {
            $decoded = @unserialize($salt, ['allowed_classes' => false]);
            $salt = is_string($decoded) ? $decoded : $salt;
        }
    }
    $hash = (string) $user['passhash'];
    $legacy = $salt === '' ? '' : sha1(strrev(substr($salt, 1, 3)) . $password . $salt);
    $valid = ($legacy !== '' && hash_equals($hash, $legacy)) || password_verify($password, $hash);
    return $valid ? (string) $user['username'] : null;
}

function sanitizeThemeName($name) {
    return preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $name));
}

function validateThemeCss(string $css): ?string
{
    $decoded = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?/', static fn($m) => mb_chr(hexdec($m[1]), 'UTF-8') ?: '', $css);
    $decoded = preg_replace('/\\\\(.)/s', '$1', (string) $decoded);
    $decoded = preg_replace('#/\*.*?\*/#s', '', (string) $decoded);
    if (preg_match('/<|@import|expression\s*\(|javascript:|behavior\s*:|-moz-binding|image-set\s*\(/i', (string) $decoded)) {
        return 'The CSS contains disallowed constructs (HTML, @import, expression(), javascript:, behavior, -moz-binding or image-set()).';
    }
    preg_match_all('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/is', (string) $decoded, $matches);
    foreach ($matches[2] as $url) {
        $url = trim($url);
        if (preg_match('#^(?:[a-z][a-z0-9+.\-]*:|//)#i', $url) && !preg_match('#^data:image/#i', $url)) {
            return 'External URLs are not allowed in theme CSS (only relative paths and data:image/ URIs).';
        }
    }
    return null;
}

function getInstalledThemes() {
    $themes = [];
    foreach (glob(THEME_DIR . '/theme-*.css') ?: [] as $file) {
        $basename = basename($file, '.css');
        $themes[] = [
            'name' => substr($basename, strlen('theme-')),
            'file' => $basename . '.css',
            'size' => filesize($file)
        ];
    }
    return $themes;
}

$message = '';
$messageType = '';

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
if (empty($_SESSION['theme_upload_csrf'])) {
    $_SESSION['theme_upload_csrf'] = bin2hex(random_bytes(16));
}
$isAuthenticated = isset($_SESSION['theme_upload_auth']) && $_SESSION['theme_upload_auth'] === true;

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: theme-upload.php');
    exit;
}

if (isset($_POST['login'])) {
    try {
        $username = verify_admin_login(connect_db($GLOBALS['dbsettings']), trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''));
    } catch (Throwable $e) {
        error_log('theme-upload login failed: ' . $e->getMessage());
        $username = null;
    }
    if ($username !== null) {
        session_regenerate_id(true);
        $_SESSION['theme_upload_auth'] = true;
        $_SESSION['theme_upload_csrf'] = bin2hex(random_bytes(16));
        header('Location: theme-upload.php');
        exit;
    }
    sleep(1);
    $message = 'Invalid administrator credentials.';
    $messageType = 'error';
}

$csrf = $_SESSION['theme_upload_csrf'];
$csrfValid = hash_equals($csrf, (string) ($_POST['csrf'] ?? ''));
if ($isAuthenticated && (isset($_POST['upload']) || isset($_POST['delete_theme'])) && !$csrfValid) {
    $message = 'Session expired. Please try again.';
    $messageType = 'error';
}

// Handle file upload
if ($isAuthenticated && $csrfValid && isset($_POST['upload']) && isset($_FILES['theme_file'])) {
    $file = $_FILES['theme_file'];
    $themeName = sanitizeThemeName($_POST['theme_name'] ?? '');
    $themeLabel = trim((string) ($_POST['theme_label'] ?? '')) ?: 'Custom Theme';
    
    // Validate
    if (empty($themeName)) {
        $message = 'Please provide a theme name (e.g., "my-theme")';
        $messageType = 'error';
    } elseif (in_array($themeName, RESERVED_THEMES, true)) {
        $message = "The theme ID '{$themeName}' is reserved for a built-in theme. Choose another ID.";
        $messageType = 'error';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Upload error: ' . $file['error'];
        $messageType = 'error';
    } elseif (!preg_match('/\.css$/i', $file['name'])) {
        $message = 'Only CSS files are allowed!';
        $messageType = 'error';
    } elseif ($file['size'] > 500000) { // 500KB max
        $message = 'File too large! Maximum 500KB.';
        $messageType = 'error';
    } elseif (($cssError = validateThemeCss((string) file_get_contents($file['tmp_name']))) !== null) {
        $message = $cssError;
        $messageType = 'error';
    } else {
        // Save file
        $targetFile = THEME_DIR . '/theme-' . $themeName . '.css';
        
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            chmod($targetFile, 0644);

            // theme-switcher.js auto-detects themes by scanning loaded CSS <link> tags.
            // theme-loader.inc.php automatically loads all themes from custom/themes/.
            // No JS modification needed.
            $message = "✓ Theme '{$themeLabel}' uploaded successfully!\n";
            $message .= "✓ CSS file auto-loaded by theme-loader.inc.php!\n";
            $message .= "Refresh your Spotweb page and select your theme from the 🎨 menu.";
            $messageType = 'success';
        } else {
            $message = 'Failed to save theme file. Check permissions on ' . THEME_DIR;
            $messageType = 'error';
        }
    }
}

// Handle theme deletion
if ($isAuthenticated && $csrfValid && isset($_POST['delete_theme'])) {
    $themeToDelete = sanitizeThemeName($_POST['theme_to_delete'] ?? '');
    $themeFile = THEME_DIR . '/theme-' . $themeToDelete . '.css';
    
    if ($themeToDelete !== '' && file_exists($themeFile) && unlink($themeFile)) {
        // No need to update JS - theme-switcher.js auto-detects and theme-loader auto-loads.
        $message = "✓ Theme deleted: {$themeToDelete}";
        $messageType = 'success';
    } else {
        $message = "Failed to delete theme: {$themeToDelete}";
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Theme Upload - Spotweb</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="password"],
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        input[type="file"] {
            padding: 10px;
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .installed-themes {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }
        
        .theme-list {
            list-style: none;
        }
        
        .theme-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        
        .theme-info {
            flex: 1;
        }
        
        .theme-name {
            font-weight: 600;
            color: #333;
        }
        
        .theme-size {
            font-size: 12px;
            color: #666;
        }
        
        .btn-delete {
            padding: 6px 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .logout-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .logout-link:hover {
            text-decoration: underline;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            margin-right: 20px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$isAuthenticated): ?>
            <h1>🔐 Authentication Required</h1>
            <p class="subtitle">Sign in with a Spotweb administrator account to upload custom themes</p>
            
            <?php if ($message): ?>
                <div class="message <?php echo h($messageType); ?>">
                    <?php echo nl2br(h($message)); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="admin" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <input type="hidden" name="login" value="1">
                <button type="submit">🔓 Unlock</button>
            </form>
            
        <?php else: ?>
            <h1>🎨 Custom Theme Upload</h1>
            <p class="subtitle">Upload CSS themes created with the Theme Customizer</p>
            
            <?php if ($message): ?>
                <div class="message <?php echo h($messageType); ?>">
                    <?php echo nl2br(h($message)); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                <div class="form-group">
                    <label for="theme_name">Theme ID:</label>
                    <input type="text" id="theme_name" name="theme_name" 
                           pattern="[a-z0-9\-]+" 
                           placeholder="my-custom-theme" 
                           required>
                    <p class="help-text">Lowercase letters, numbers, and hyphens only</p>
                </div>
                
                <div class="form-group">
                    <label for="theme_label">Theme Label:</label>
                    <input type="text" id="theme_label" name="theme_label" 
                           placeholder="My Custom Theme" 
                           required>
                    <p class="help-text">This appears in the theme selector</p>
                </div>
                
                <div class="form-group">
                    <label for="theme_file">CSS File:</label>
                    <input type="file" id="theme_file" name="theme_file" 
                           accept=".css" 
                           required>
                    <p class="help-text">Upload the CSS file from Theme Customizer (max 500KB)</p>
                </div>
                
                <button type="submit" name="upload">📤 Upload Theme</button>
            </form>
            
            <?php 
            $installedThemes = getInstalledThemes();
            if (!empty($installedThemes)): 
            ?>
                <div class="installed-themes">
                    <h2 style="margin-bottom: 15px; font-size: 18px; color: #333;">📦 Installed Custom Themes</h2>
                    <ul class="theme-list">
                        <?php foreach ($installedThemes as $theme): ?>
                            <li class="theme-item">
                                <div class="theme-info">
                                    <div class="theme-name"><?php echo h($theme['name']); ?><?php echo in_array($theme['name'], RESERVED_THEMES, true) ? ' (overrides built-in)' : ''; ?></div>
                                    <div class="theme-size"><?php echo round($theme['size'] / 1024, 1); ?> KB</div>
                                </div>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                                    <input type="hidden" name="theme_to_delete" value="<?php echo h($theme['name']); ?>">
                                    <button type="submit" name="delete_theme" class="btn-delete" 
                                            onclick="return confirm('Delete theme: <?php echo h($theme['name']); ?>?')">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div>
                <a href="theme-customizer.html" class="back-link">← Back to Theme Customizer</a>
                <a href="?logout" class="logout-link">🔒 Logout</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
