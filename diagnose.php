<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to display information in a readable format
function displayInfo($title, $data) {
    echo "<h3>$title</h3>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}

// Start HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Spotweb Diagnostic Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>Spotweb Diagnostic Tool</h1>";

// Check if we can access basic PHP functionality
echo "<h2>Basic PHP Check</h2>";
echo "<p class='success'>PHP is working correctly if you can see this message.</p>";

// Check PHP version
echo "<p>PHP Version: " . phpversion() . "</p>";

// Check if we can access the file system
echo "<h2>File System Check</h2>";
try {
    $baseDir = __DIR__;
    echo "<p>Base directory: $baseDir</p>";
    
    $files = scandir($baseDir);
    echo "<p class='success'>Successfully accessed directory. Found " . count($files) . " files/directories.</p>";
    
    // Check if critical files exist
    $criticalFiles = ['index.php', 'vendor/autoload.php', 'templates/we1rdo/includes/header.inc.php'];
    echo "<h3>Critical Files Check</h3>";
    echo "<ul>";
    foreach ($criticalFiles as $file) {
        $path = $baseDir . '/' . $file;
        if (file_exists($path)) {
            echo "<li class='success'>$file exists</li>";
        } else {
            echo "<li class='error'>$file is missing!</li>";
        }
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p class='error'>Error accessing file system: " . $e->getMessage() . "</p>";
}

// Try to include autoloader to see if it works
echo "<h2>Autoloader Check</h2>";
try {
    require_once __DIR__.'/vendor/autoload.php';
    echo "<p class='success'>Successfully loaded autoloader.</p>";
} catch (Exception $e) {
    echo "<p class='error'>Error loading autoloader: " . $e->getMessage() . "</p>";
}

// Try to initialize basic Spotweb classes
echo "<h2>Spotweb Classes Check</h2>";
try {
    // Check if Bootstrap class exists
    if (class_exists('Bootstrap')) {
        echo "<p class='success'>Bootstrap class exists.</p>";
    } else {
        echo "<p class='error'>Bootstrap class not found!</p>";
    }
    
    // Check if SpotTiming class exists
    if (class_exists('SpotTiming')) {
        echo "<p class='success'>SpotTiming class exists.</p>";
    } else {
        echo "<p class='error'>SpotTiming class not found!</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>Error checking Spotweb classes: " . $e->getMessage() . "</p>";
}

// Check for performance-related files that might be causing issues
echo "<h2>Performance Files Check</h2>";
$performanceFiles = [
    '/performance.php',
    '/templates/we1rdo/js/performance-optimizations.js',
    '/templates/we1rdo/js/performance-service-worker.js',
    '/lib/services/Performance/Services_Performance_Monitor.php',
    '/lib/page/SpotPage_Performance.php',
    '/templates/we1rdo/performance-dashboard.inc.php'
];

echo "<ul>";
foreach ($performanceFiles as $file) {
    $path = $baseDir . $file;
    if (file_exists($path)) {
        echo "<li class='error'>$file exists and might be causing issues</li>";
    } else {
        echo "<li class='success'>$file has been removed</li>";
    }
}
echo "</ul>";

// Check for performance-related code in index.php
echo "<h2>Index.php Check</h2>";
try {
    $indexContent = file_get_contents($baseDir . '/index.php');
    if (strpos($indexContent, 'SpotPage_Performance') !== false) {
        echo "<p class='error'>index.php contains references to SpotPage_Performance</p>";
    } else {
        echo "<p class='success'>index.php does not contain references to SpotPage_Performance</p>";
    }
    
    if (strpos($indexContent, 'case \'performance\'') !== false) {
        echo "<p class='error'>index.php contains a case for 'performance'</p>";
    } else {
        echo "<p class='success'>index.php does not contain a case for 'performance'</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Error checking index.php: " . $e->getMessage() . "</p>";
}

// Display server information
echo "<h2>Server Information</h2>";
displayInfo("Server Variables", $_SERVER);

echo "</body>
</html>";
?>
