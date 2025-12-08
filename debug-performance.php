<?php
/**
 * Debug Performance Page
 * 
 * This script helps diagnose issues with the performance page
 */

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create an error log file
$errorLogFile = __DIR__ . '/performance-debug.log';
file_put_contents($errorLogFile, "Debug started at: " . date('Y-m-d H:i:s') . "\n");
ini_set('error_log', $errorLogFile);

// Set up error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($errorLogFile) {
    $message = date('Y-m-d H:i:s') . " - Error: [$errno] $errstr in $errfile on line $errline\n";
    file_put_contents($errorLogFile, $message, FILE_APPEND);
    return false; // Allow the standard error handler to run as well
});

// Set up exception handler
set_exception_handler(function($exception) use ($errorLogFile) {
    $message = date('Y-m-d H:i:s') . " - Exception: " . $exception->getMessage() . 
              " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n" .
              "Stack trace:\n" . $exception->getTraceAsString() . "\n";
    file_put_contents($errorLogFile, $message, FILE_APPEND);
});

// HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Performance Page</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
        .section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Debug Performance Page</h1>";

// Step 1: Check if the Performance class exists
echo "<div class='section'>";
echo "<h2>Step 1: Check Performance Classes</h2>";

$classFiles = [
    'SpotPage_Performance' => __DIR__ . '/lib/page/SpotPage_Performance.php',
    'Services_Performance_Monitor' => __DIR__ . '/lib/services/Performance/Services_Performance_Monitor.php',
];

foreach ($classFiles as $className => $filePath) {
    echo "<h3>Checking $className</h3>";
    
    if (file_exists($filePath)) {
        echo "<p>File exists: " . basename($filePath) . "</p>";
        
        // Include the file to check for syntax errors
        try {
            include_once($filePath);
            echo "<p>File included successfully.</p>";
            
            // Check if class exists
            if (class_exists($className)) {
                echo "<p>Class $className exists.</p>";
            } else {
                echo "<p style='color: red;'>Class $className does not exist after including the file!</p>";
            }
        } catch (Throwable $e) {
            echo "<p style='color: red;'>Error including file: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>File does not exist: " . basename($filePath) . "</p>";
    }
}
echo "</div>";

// Step 2: Check the index.php switch case
echo "<div class='section'>";
echo "<h2>Step 2: Check index.php Switch Case</h2>";

$indexPath = __DIR__ . '/index.php';
if (file_exists($indexPath)) {
    $indexContent = file_get_contents($indexPath);
    
    // Check for performance case
    if (preg_match('/case\s+[\'"]performance[\'"]\s*:(.*?)break;/s', $indexContent, $matches)) {
        echo "<p>Found performance case in index.php:</p>";
        echo "<pre>" . htmlspecialchars(trim($matches[0])) . "</pre>";
    } else {
        echo "<p style='color: red;'>Could not find performance case in index.php!</p>";
    }
} else {
    echo "<p style='color: red;'>index.php not found!</p>";
}
echo "</div>";

// Step 3: Check for template file
echo "<div class='section'>";
echo "<h2>Step 3: Check Template File</h2>";

$templatePath = __DIR__ . '/templates/we1rdo/performance-dashboard.inc.php';
if (file_exists($templatePath)) {
    echo "<p>Template file exists: " . basename($templatePath) . "</p>";
    
    // Check template content
    $templateContent = file_get_contents($templatePath);
    if (strpos($templateContent, 'includeTemplate(\'includes/header\')') !== false) {
        echo "<p>Template includes header.</p>";
    } else {
        echo "<p style='color: red;'>Template does not include header!</p>";
    }
    
    if (strpos($templateContent, 'includeTemplate(\'includes/footer\')') !== false) {
        echo "<p>Template includes footer.</p>";
    } else {
        echo "<p style='color: red;'>Template does not include footer!</p>";
    }
} else {
    echo "<p style='color: red;'>Template file does not exist: " . basename($templatePath) . "</p>";
}
echo "</div>";

// Step 4: Check for required dependencies
echo "<div class='section'>";
echo "<h2>Step 4: Check Required Dependencies</h2>";

// Check if SpotPage_Abs exists (parent class of SpotPage_Performance)
if (class_exists('SpotPage_Abs')) {
    echo "<p>SpotPage_Abs class exists.</p>";
} else {
    echo "<p style='color: red;'>SpotPage_Abs class does not exist!</p>";
}

// Check if Services_Settings_Container exists
if (class_exists('Services_Settings_Container')) {
    echo "<p>Services_Settings_Container class exists.</p>";
} else {
    echo "<p style='color: red;'>Services_Settings_Container class does not exist!</p>";
}

// Check if Dao_Factory exists
if (class_exists('Dao_Factory')) {
    echo "<p>Dao_Factory class exists.</p>";
} else {
    echo "<p style='color: red;'>Dao_Factory class does not exist!</p>";
}
echo "</div>";

// Step 5: Try to manually instantiate the classes
echo "<div class='section'>";
echo "<h2>Step 5: Try to Instantiate Classes</h2>";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "<p>Autoloader included successfully.</p>";
    
    // Try to create a Settings object
    if (class_exists('Services_Settings_Container')) {
        echo "<p>Attempting to create a Settings object...</p>";
        $settings = new Services_Settings_Container();
        echo "<p>Settings object created successfully.</p>";
    }
    
    // Try to create a Performance Monitor object
    if (class_exists('Services_Performance_Monitor') && isset($settings)) {
        echo "<p>Attempting to create a Performance Monitor object...</p>";
        try {
            $daoFactory = null; // We don't have a real DAO factory here
            $monitor = new Services_Performance_Monitor($settings, $daoFactory);
            echo "<p>Performance Monitor object created successfully.</p>";
        } catch (Throwable $e) {
            echo "<p style='color: red;'>Error creating Performance Monitor: " . $e->getMessage() . "</p>";
        }
    }
} catch (Throwable $e) {
    echo "<p style='color: red;'>Error during instantiation tests: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Display the error log
echo "<div class='section'>";
echo "<h2>Error Log</h2>";
if (file_exists($errorLogFile)) {
    $logContent = file_get_contents($errorLogFile);
    echo "<pre>" . htmlspecialchars($logContent) . "</pre>";
} else {
    echo "<p>No error log available.</p>";
}
echo "</div>";

echo "<p><a href='index.php'>Return to Spotweb</a></p>";
echo "</body></html>";
?>
