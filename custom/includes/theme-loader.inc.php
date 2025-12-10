<?php
/**
 * Spotweb Custom Theme Loader
 * 
 * This file is the ONLY integration point between core Spotweb and custom themes.
 * It loads custom theme CSS files and injects the theme switcher JavaScript.
 * 
 * Integration: Add this line to templates/we1rdo/includes/header.inc.php:
 * <?php include_once(__DIR__ . '/../../../custom/includes/theme-loader.inc.php'); ?>
 */

// Base paths
$customBase = realpath(__DIR__ . '/../..');
$preinstalledThemesPath = $customBase . '/themes/preinstalled';
$customThemesPath = $customBase . '/themes';
$jsPath = $customBase . '/js';

// Load pre-installed theme CSS files
$preinstalledThemes = [
    'dark',
    'midnight-ocean',
    'cyberpunk',
    'nord',
    'dracula',
    'forest',
    'sunset'
];

foreach ($preinstalledThemes as $theme) {
    $themeFile = $preinstalledThemesPath . '/theme-' . $theme . '.css';
    if (file_exists($themeFile)) {
        echo "<link rel='stylesheet' type='text/css' href='custom/themes/preinstalled/theme-{$theme}.css'>\n";
    }
}

// Load custom user themes (from custom/themes/ root, not preinstalled/)
$customThemeFiles = glob($customThemesPath . '/theme-*.css');
if ($customThemeFiles) {
    foreach ($customThemeFiles as $themeFile) {
        $themeName = basename($themeFile);
        echo "<link rel='stylesheet' type='text/css' href='custom/themes/{$themeName}'>\n";
    }
}

// Load theme switcher JavaScript
$themeSwitcherJs = $jsPath . '/theme-switcher.js';
if (file_exists($themeSwitcherJs)) {
    echo "<script src='custom/js/theme-switcher.js'></script>\n";
}

// Add theme switcher styles (inline to avoid extra file)
?>
<style>
/* Theme Switcher Styles */
.theme-dropdown {
    position: relative;
    display: inline-block;
}

.theme-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: inherit;
    text-decoration: none;
}

.theme-toggle:hover {
    background: rgba(0, 0, 0, 0.3);
    border-color: rgba(255, 255, 255, 0.2);
}

.theme-icon {
    font-size: 18px;
}

.theme-name {
    font-size: 14px;
    font-weight: 500;
}

.theme-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    background: #2d2d2d;
    border: 1px solid #444;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    min-width: 200px;
    z-index: 9999;
    overflow: hidden;
}

.theme-menu.show {
    display: block;
    animation: slideDown 0.2s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.theme-menu ul {
    list-style: none;
    margin: 0;
    padding: 8px 0;
}

.theme-menu li {
    padding: 0;
}

.theme-menu a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: #e0e0e0;
    text-decoration: none;
    transition: background 0.2s ease;
}

.theme-menu a:hover {
    background: rgba(255, 255, 255, 0.1);
}

.theme-menu a.active {
    background: rgba(100, 150, 255, 0.2);
    border-left: 3px solid #6496ff;
}

.theme-menu .theme-icon {
    font-size: 16px;
}

.theme-menu .theme-name {
    flex: 1;
    font-size: 14px;
}
</style>
<?php
