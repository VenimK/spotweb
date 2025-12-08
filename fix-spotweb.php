<?php
/**
 * Spotweb Recovery Script
 * 
 * This script removes performance optimizations that might be causing HTTP 500 errors
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base directory
$baseDir = __DIR__;

// Function to display a message
function showMessage($type, $message) {
    echo "<div class='$type'>$message</div>";
}

// Start HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Spotweb Recovery</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        .success {
            color: green;
            background-color: #e8f5e9;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: red;
            background-color: #ffebee;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .info {
            color: #0288d1;
            background-color: #e1f5fe;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>Spotweb Recovery</h1>
    <p>This script will remove performance optimizations that might be causing HTTP 500 errors.</p>";

// Step 1: Remove service worker registration from header.inc.php
$headerPath = $baseDir . '/templates/we1rdo/includes/header.inc.php';
if (file_exists($headerPath)) {
    $headerContent = file_get_contents($headerPath);
    
    // Find and remove the service worker script block
    $pattern = '/\s*<script>\s*\/\/\s*Service Worker registratie.*?<\/script>\s*/s';
    $headerContent = preg_replace($pattern, '', $headerContent);
    
    // Also remove the performance-optimizations.js script
    $pattern = '/\s*<script type=\'text\/javascript\' src=\'templates\/we1rdo\/js\/performance-optimizations\.js\'><\/script>\s*/';
    $headerContent = preg_replace($pattern, '', $headerContent);
    
    if (file_put_contents($headerPath, $headerContent)) {
        showMessage('success', 'Successfully removed service worker registration from header file.');
    } else {
        showMessage('error', 'Failed to update header file.');
    }
} else {
    showMessage('error', 'Header file not found: ' . $headerPath);
}

// Step 2: Remove performance.php redirect file if it exists
$performancePath = $baseDir . '/performance.php';
if (file_exists($performancePath)) {
    if (unlink($performancePath)) {
        showMessage('success', 'Successfully removed performance.php redirect file.');
    } else {
        showMessage('error', 'Failed to remove performance.php file.');
    }
} else {
    showMessage('info', 'Performance.php file not found (already removed).');
}

// Step 3: Restore original index.php if backup exists
$indexBackupPath = $baseDir . '/index.php.bak';
if (file_exists($indexBackupPath)) {
    if (copy($indexBackupPath, $baseDir . '/index.php')) {
        showMessage('success', 'Successfully restored original index.php file.');
    } else {
        showMessage('error', 'Failed to restore index.php file.');
    }
} else {
    showMessage('info', 'No index.php backup found, no changes made to index.php.');
}

// Step 4: Clear browser cache instructions
echo "<div class='info'>
    <h3>Next Steps</h3>
    <p>To complete the recovery process:</p>
    <ol>
        <li><strong>Clear your browser cache</strong> - This is very important to remove any cached service worker.</li>
        <li>In Chrome: Go to Settings → Privacy and Security → Clear browsing data → Select 'Cached images and files' → Click 'Clear data'</li>
        <li>In Firefox: Go to Options → Privacy & Security → Cookies and Site Data → Clear Data → Select 'Cached Web Content' → Click 'Clear'</li>
        <li>In Safari: Go to Safari → Preferences → Advanced → Show Develop menu → Develop → Empty Caches</li>
    </ol>
</div>";

// Step 5: Provide link back to Spotweb
echo "<p><a href='index.php' style='display: inline-block; padding: 10px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Return to Spotweb</a></p>";

echo "</body></html>";
?>
