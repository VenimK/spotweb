<?php
/**
 * Spotweb Emergency Recovery Script
 * 
 * This script performs a thorough cleanup of performance optimizations
 * and restores Spotweb to a working state
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base directory
$baseDir = __DIR__;

// Function to display a message
function showMessage($type, $message) {
    echo "<div class='$type'>$message</div>";
}

// Function to check if a file exists and remove it
function removeFile($path) {
    if (file_exists($path)) {
        if (unlink($path)) {
            showMessage('success', "Successfully removed: " . basename($path));
            return true;
        } else {
            showMessage('error', "Failed to remove: " . basename($path));
            return false;
        }
    } else {
        showMessage('info', "File not found (already removed): " . basename($path));
        return true;
    }
}

// Start HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Spotweb Emergency Recovery</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1, h2 {
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
        .warning {
            color: #FF8F00;
            background-color: #FFF8E1;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        code {
            background: #f5f5f5;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>Spotweb Emergency Recovery</h1>
    <p>This script will perform a thorough cleanup to restore your Spotweb installation.</p>";

// Step 1: Check PHP version and extensions
echo "<h2>Step 1: System Check</h2>";
echo "<div class='info'>PHP Version: " . phpversion() . "</div>";

// Step 2: Remove all performance-related files
echo "<h2>Step 2: Removing Performance Optimization Files</h2>";

// List of files to remove
$filesToRemove = [
    // Redirect files
    $baseDir . '/performance.php',
    
    // JavaScript files
    $baseDir . '/templates/we1rdo/js/performance-optimizations.js',
    $baseDir . '/templates/we1rdo/js/performance-service-worker.js',
    
    // PHP class files
    $baseDir . '/lib/services/Performance/Services_Performance_Monitor.php',
    $baseDir . '/lib/services/Caching/Services_Caching_Manager.php',
    $baseDir . '/lib/page/SpotPage_Performance.php',
    
    // Template files
    $baseDir . '/templates/we1rdo/performance-dashboard.inc.php',
    
    // Installation scripts
    $baseDir . '/install-performance-optimizations.php',
    $baseDir . '/install-performance-alt.php'
];

// Remove each file
foreach ($filesToRemove as $file) {
    removeFile($file);
}

// Step 3: Fix header.inc.php
echo "<h2>Step 3: Fixing Header File</h2>";
$headerPath = $baseDir . '/templates/we1rdo/includes/header.inc.php';

if (file_exists($headerPath)) {
    // Create a backup of the header file
    $backupPath = $headerPath . '.bak.' . time();
    if (copy($headerPath, $backupPath)) {
        showMessage('success', "Created backup of header file: " . basename($backupPath));
    } else {
        showMessage('warning', "Could not create backup of header file");
    }
    
    // Read the header file
    $headerContent = file_get_contents($headerPath);
    
    // Remove any performance-related scripts
    $patterns = [
        '/\s*<script type=[\'"]text\/javascript[\'"] src=[\'"]templates\/we1rdo\/js\/performance-optimizations\.js[\'"]><\/script>\s*/i',
        '/\s*<script>\s*\/\/\s*Service Worker registratie.*?<\/script>\s*/s'
    ];
    
    $originalLength = strlen($headerContent);
    foreach ($patterns as $pattern) {
        $headerContent = preg_replace($pattern, '', $headerContent);
    }
    
    if (strlen($headerContent) != $originalLength) {
        // Write the modified content back to the file
        if (file_put_contents($headerPath, $headerContent)) {
            showMessage('success', "Successfully cleaned header file");
        } else {
            showMessage('error', "Failed to write to header file");
        }
    } else {
        showMessage('info', "No changes needed in header file");
    }
} else {
    showMessage('error', "Header file not found: " . $headerPath);
}

// Step 4: Fix index.php if needed
echo "<h2>Step 4: Checking index.php</h2>";
$indexPath = $baseDir . '/index.php';

if (file_exists($indexPath)) {
    // Create a backup of the index file
    $backupPath = $indexPath . '.bak.' . time();
    if (copy($indexPath, $backupPath)) {
        showMessage('success', "Created backup of index.php: " . basename($backupPath));
    } else {
        showMessage('warning', "Could not create backup of index.php");
    }
    
    // Read the index file
    $indexContent = file_get_contents($indexPath);
    
    // Check for performance-related code
    $performancePatterns = [
        '/case\s+[\'"]performance[\'"]\s*:\s*.*?break;/s',
        '/require_once.*?SpotPage_Performance\.php.*?;/'
    ];
    
    $modified = false;
    foreach ($performancePatterns as $pattern) {
        if (preg_match($pattern, $indexContent)) {
            $indexContent = preg_replace($pattern, '', $indexContent);
            $modified = true;
        }
    }
    
    if ($modified) {
        // Write the modified content back to the file
        if (file_put_contents($indexPath, $indexContent)) {
            showMessage('success', "Successfully removed performance-related code from index.php");
        } else {
            showMessage('error', "Failed to write to index.php");
        }
    } else {
        showMessage('info', "No performance-related code found in index.php");
    }
} else {
    showMessage('error', "index.php not found");
}

// Step 5: Check for service worker in browser
echo "<h2>Step 5: Browser Cache Instructions</h2>";
echo "<div class='warning'>
    <p><strong>Important:</strong> You need to clear your browser cache to remove any cached service workers:</p>
    <ol>
        <li>Open your browser's developer tools (F12 or Right-click → Inspect)</li>
        <li>Go to the Application tab</li>
        <li>Select 'Service Workers' in the left sidebar</li>
        <li>Click 'Unregister' next to any Spotweb service workers</li>
        <li>Then clear your browser cache:
            <ul>
                <li>Chrome: Settings → Privacy and Security → Clear browsing data</li>
                <li>Firefox: Options → Privacy & Security → Clear Data</li>
                <li>Safari: Safari → Preferences → Advanced → Empty Caches</li>
            </ul>
        </li>
    </ol>
</div>";

// Step 6: Provide link back to Spotweb
echo "<h2>Next Steps</h2>";
echo "<p>After clearing your browser cache, try accessing Spotweb again:</p>";
echo "<p><a href='index.php' style='display: inline-block; padding: 10px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Go to Spotweb</a></p>";

// Display technical information for debugging
echo "<h2>Technical Information</h2>";
echo "<div class='info'>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>PHP SAPI: " . php_sapi_name() . "</p>";
echo "</div>";

echo "</body></html>";
?>
