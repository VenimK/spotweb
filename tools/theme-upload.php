<?php
/**
 * Spotweb Custom Theme Uploader
 * Allows users to upload custom CSS themes generated from the Theme Customizer
 */

// Security: Simple password protection (change this!)
define('UPLOAD_PASSWORD', 'spotweb123');

// Paths
define('THEME_DIR', __DIR__ . '/../templates/we1rdo/css');
define('JS_FILE', __DIR__ . '/../templates/we1rdo/js/theme-switcher.js');
define('HEADER_FILE', __DIR__ . '/../templates/we1rdo/includes/header.inc.php');

$message = '';
$messageType = '';

// Handle password check
session_start();
$isAuthenticated = isset($_SESSION['theme_upload_auth']) && $_SESSION['theme_upload_auth'] === true;

if (isset($_POST['password'])) {
    if ($_POST['password'] === UPLOAD_PASSWORD) {
        $_SESSION['theme_upload_auth'] = true;
        $isAuthenticated = true;
    } else {
        $message = 'Invalid password!';
        $messageType = 'error';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: theme-upload.php');
    exit;
}

// Handle file upload
if ($isAuthenticated && isset($_POST['upload']) && isset($_FILES['theme_file'])) {
    $file = $_FILES['theme_file'];
    $themeName = sanitizeThemeName($_POST['theme_name'] ?? '');
    $themeLabel = $_POST['theme_label'] ?? 'Custom Theme';
    $themeIcon = $_POST['theme_icon'] ?? '🎨';
    
    // Validate
    if (empty($themeName)) {
        $message = 'Please provide a theme name (e.g., "my-theme")';
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
    } else {
        // Save file
        $targetFile = THEME_DIR . '/theme-' . $themeName . '.css';
        
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            chmod($targetFile, 0644);
            
            // Update theme-switcher.js and header.inc.php
            $switcherUpdated = updateThemeSwitcher($themeName, $themeLabel, $themeIcon);
            $headerUpdated = updateHeaderFile($themeName);
            
            if ($switcherUpdated && $headerUpdated) {
                $message = "✓ Theme '{$themeLabel}' uploaded successfully!<br>";
                $message .= "✓ Theme switcher updated automatically!<br>";
                $message .= "✓ Header CSS link added automatically!<br>";
                $message .= "Refresh your Spotweb page and select your theme from the 🎨 menu.";
                $messageType = 'success';
            } elseif ($switcherUpdated && !$headerUpdated) {
                $message = "✓ Theme uploaded and added to switcher!<br>";
                $message .= "⚠ Could not auto-update header.inc.php. Add this line manually:<br>";
                $message .= "<code>&lt;link rel='stylesheet' type='text/css' href='templates/we1rdo/css/theme-{$themeName}.css'&gt;</code>";
                $messageType = 'warning';
            } else {
                $message = "✓ Theme uploaded to: {$targetFile}<br>";
                $message .= "⚠ Could not auto-update theme switcher and header. Add manually.";
                $messageType = 'warning';
            }
        } else {
            $message = 'Failed to save theme file. Check permissions on ' . THEME_DIR;
            $messageType = 'error';
        }
    }
}

// Handle theme deletion
if ($isAuthenticated && isset($_POST['delete_theme'])) {
    $themeToDelete = sanitizeThemeName($_POST['theme_to_delete']);
    $themeFile = THEME_DIR . '/theme-' . $themeToDelete . '.css';
    
    if (file_exists($themeFile) && unlink($themeFile)) {
        removeFromThemeSwitcher($themeToDelete);
        removeFromHeaderFile($themeToDelete);
        $message = "✓ Theme deleted: {$themeToDelete}";
        $messageType = 'success';
    } else {
        $message = "Failed to delete theme: {$themeToDelete}";
        $messageType = 'error';
    }
}

function sanitizeThemeName($name) {
    return preg_replace('/[^a-z0-9\-]/', '', strtolower($name));
}

function updateThemeSwitcher($themeName, $themeLabel, $themeIcon) {
    if (!file_exists(JS_FILE) || !is_writable(JS_FILE)) {
        return false;
    }
    
    $content = file_get_contents(JS_FILE);
    
    // Check if theme already exists
    if (strpos($content, "id: '{$themeName}'") !== false) {
        return true; // Already exists
    }
    
    // Find ]; that closes the themes array and insert new theme before it
    // Use negative lookbehind to avoid matching other ]; in the file
    $newTheme = "    { id: '{$themeName}', name: '{$themeLabel}', icon: '{$themeIcon}' }";
    
    // Pattern: Find the closing of themes array (];)
    // Replace it with: comma + newline + new theme + newline + ];
    // But only if the previous line doesn't already end with a comma
    
    // First, find position of themes array closing
    $arrayPos = strpos($content, 'const themes = [');
    if ($arrayPos === false) {
        return false;
    }
    
    // Find the matching ]; after the themes array starts
    $closingPos = strpos($content, '];', $arrayPos);
    if ($closingPos === false) {
        return false;
    }
    
    // Get the part before ];
    $beforeClosing = substr($content, 0, $closingPos);
    $afterClosing = substr($content, $closingPos);
    
    // Check if we need to add a comma to the last line
    $beforeClosing = rtrim($beforeClosing);
    if (substr($beforeClosing, -1) !== ',') {
        $beforeClosing .= ',';
    }
    
    // Rebuild the file
    $newContent = $beforeClosing . "\n" . $newTheme . "\n" . $afterClosing;
    
    return file_put_contents(JS_FILE, $newContent) !== false;
}

function removeFromThemeSwitcher($themeName) {
    if (!file_exists(JS_FILE) || !is_writable(JS_FILE)) {
        return false;
    }
    
    $content = file_get_contents(JS_FILE);
    $pattern = "/\\s*\\{[^}]*id:\\s*['\"]" . preg_quote($themeName, '/') . "['\"][^}]*\\},?\\n?/";
    $newContent = preg_replace($pattern, '', $content);
    
    if ($newContent && $newContent !== $content) {
        file_put_contents(JS_FILE, $newContent);
        return true;
    }
    
    return false;
}

function updateHeaderFile($themeName) {
    if (!file_exists(HEADER_FILE) || !is_writable(HEADER_FILE)) {
        return false;
    }
    
    $content = file_get_contents(HEADER_FILE);
    
    // Check if link already exists
    if (strpos($content, "theme-{$themeName}.css") !== false) {
        return true; // Already exists
    }
    
    // Find the last theme CSS link and add after it
    // Look for pattern: <link rel='stylesheet' type='text/css' href='templates/we1rdo/css/theme-*.css'>
    $newLink = "<link rel='stylesheet' type='text/css' href='templates/we1rdo/css/theme-{$themeName}.css'>";
    
    // Find the last occurrence of a theme CSS link
    $pattern = "/<link rel='stylesheet' type='text\/css' href='templates\/we1rdo\/css\/theme-[^']+\.css'>/";
    preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
    
    if (!empty($matches[0])) {
        // Get the last match
        $lastMatch = end($matches[0]);
        $insertPos = $lastMatch[1] + strlen($lastMatch[0]);
        
        // Insert new link after the last theme link
        $newContent = substr($content, 0, $insertPos) . "\n" . $newLink . substr($content, $insertPos);
        
        return file_put_contents(HEADER_FILE, $newContent) !== false;
    }
    
    return false;
}

function removeFromHeaderFile($themeName) {
    if (!file_exists(HEADER_FILE) || !is_writable(HEADER_FILE)) {
        return false;
    }
    
    $content = file_get_contents(HEADER_FILE);
    
    // Remove the CSS link line for this theme
    $pattern = "/\n?<link rel='stylesheet' type='text\/css' href='templates\/we1rdo\/css\/theme-" . preg_quote($themeName, '/') . "\.css'>/";
    $newContent = preg_replace($pattern, '', $content);
    
    if ($newContent && $newContent !== $content) {
        file_put_contents(HEADER_FILE, $newContent);
        return true;
    }
    
    return false;
}

function getInstalledThemes() {
    $themes = [];
    $defaultThemes = ['dark', 'midnight-ocean', 'cyberpunk', 'nord', 'dracula', 'forest', 'sunset'];
    
    $files = glob(THEME_DIR . '/theme-*.css');
    foreach ($files as $file) {
        $basename = basename($file, '.css');
        $themeName = str_replace('theme-', '', $basename);
        
        if (!in_array($themeName, $defaultThemes)) {
            $themes[] = [
                'name' => $themeName,
                'file' => $basename . '.css',
                'size' => filesize($file)
            ];
        }
    }
    
    return $themes;
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
            <p class="subtitle">Enter password to upload custom themes</p>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required autofocus>
                    <p class="help-text">Default: spotweb123 (change in theme-upload.php)</p>
                </div>
                <button type="submit">🔓 Unlock</button>
            </form>
            
        <?php else: ?>
            <h1>🎨 Custom Theme Upload</h1>
            <p class="subtitle">Upload CSS themes created with the Theme Customizer</p>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
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
                    <label for="theme_icon">Theme Icon:</label>
                    <input type="text" id="theme_icon" name="theme_icon" 
                           value="🎨" 
                           maxlength="2">
                    <p class="help-text">Emoji that appears next to the theme name</p>
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
                                    <div class="theme-name"><?php echo htmlspecialchars($theme['name']); ?></div>
                                    <div class="theme-size"><?php echo round($theme['size'] / 1024, 1); ?> KB</div>
                                </div>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="theme_to_delete" value="<?php echo htmlspecialchars($theme['name']); ?>">
                                    <button type="submit" name="delete_theme" class="btn-delete" 
                                            onclick="return confirm('Delete theme: <?php echo htmlspecialchars($theme['name']); ?>?')">
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
