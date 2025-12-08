<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define the base directory
$baseDir = __DIR__;

// Function to display a message
function showMessage($message, $type = 'info') {
    echo "<div style='padding: 10px; margin: 5px 0; border-radius: 4px; background-color: " . 
         ($type == 'success' ? '#e8f5e9' : ($type == 'error' ? '#ffebee' : '#e1f5fe')) . 
         "; color: " . ($type == 'success' ? 'green' : ($type == 'error' ? 'red' : 'blue')) . ";'>" . 
         $message . "</div>";
}

// Start HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Spotweb Quick Fix</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1 { color: #333; }
    </style>
</head>
<body>
    <h1>Spotweb Quick Fix</h1>";

// Step 1: Fix index.php - This is the most critical step
$indexPath = $baseDir . '/index.php';
if (file_exists($indexPath)) {
    // Create a backup
    copy($indexPath, $indexPath . '.bak');
    
    // Read the content
    $content = file_get_contents($indexPath);
    
    // Look for performance case in the switch statement
    if (strpos($content, "case 'performance'") !== false) {
        // Remove the entire case block
        $pattern = "/case\s+'performance'.*?break;\s*\/\/\s*performance/s";
        $content = preg_replace($pattern, '', $content);
        
        // Write back to file
        if (file_put_contents($indexPath, $content)) {
            showMessage("Successfully removed 'performance' case from index.php", 'success');
        } else {
            showMessage("Failed to write to index.php", 'error');
        }
    } else {
        showMessage("No 'performance' case found in index.php", 'info');
    }
} else {
    showMessage("index.php not found!", 'error');
}

// Step 2: Check for and remove any lingering performance files
$filesToCheck = [
    '/lib/page/SpotPage_Performance.php',
    '/templates/we1rdo/performance-dashboard.inc.php',
    '/performance.php'
];

foreach ($filesToCheck as $file) {
    $fullPath = $baseDir . $file;
    if (file_exists($fullPath)) {
        if (unlink($fullPath)) {
            showMessage("Removed: $file", 'success');
        } else {
            showMessage("Failed to remove: $file", 'error');
        }
    }
}

// Step 3: Provide a link back to Spotweb
echo "<p>Try accessing Spotweb now:</p>";
echo "<p><a href='index.php' style='display: inline-block; padding: 10px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Go to Spotweb</a></p>";

echo "</body>
</html>";
?>
