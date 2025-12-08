<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to create a simple error logger
function createErrorLogger() {
    $errorLogFile = __DIR__ . '/spotweb-error.log';
    
    // Create or clear the error log file
    file_put_contents($errorLogFile, '');
    
    // Set error log file
    ini_set('error_log', $errorLogFile);
    
    // Set up error handler
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        $message = date('Y-m-d H:i:s') . " - Error: [$errno] $errstr in $errfile on line $errline\n";
        error_log($message);
    });
    
    // Set up exception handler
    set_exception_handler(function($exception) {
        $message = date('Y-m-d H:i:s') . " - Exception: " . $exception->getMessage() . 
                  " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
        error_log($message);
    });
    
    return $errorLogFile;
}

// Create the error logger
$errorLogFile = createErrorLogger();

// Start HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Spotweb Error Logger</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        .button {
            display: inline-block;
            padding: 10px 15px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 0;
        }
        .info {
            background-color: #e1f5fe;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <h1>Spotweb Error Logger</h1>
    <div class='info'>
        <p>This script has set up an error logger that will capture PHP errors when you access your Spotweb installation.</p>
        <p>Error log file: <code>$errorLogFile</code></p>
        <p>Steps to diagnose the issue:</p>
        <ol>
            <li>Click the button below to try accessing your Spotweb installation</li>
            <li>Return to this page to view any errors that were captured</li>
        </ol>
    </div>
    
    <a href='index.php' class='button' target='_blank'>Access Spotweb</a>
    
    <h2>Error Log Contents</h2>";

// Check if the error log file exists and has content
if (file_exists($errorLogFile)) {
    $logContent = file_get_contents($errorLogFile);
    if (!empty($logContent)) {
        echo "<pre>" . htmlspecialchars($logContent) . "</pre>";
    } else {
        echo "<p>No errors logged yet. Try accessing Spotweb first.</p>";
    }
} else {
    echo "<p>Error log file could not be created.</p>";
}

// Create a simple wrapper for index.php that will catch errors
$wrapperPath = __DIR__ . '/index-debug.php';
$wrapperContent = '<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set("error_log", "' . $errorLogFile . '");

try {
    // Include the original index.php
    include(__DIR__ . "/index.php");
} catch (Throwable $e) {
    // Log the error
    error_log(date("Y-m-d H:i:s") . " - Fatal error: " . $e->getMessage() . 
              " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Display error
    echo "<h1>Error Detected</h1>";
    echo "<p>An error occurred while loading Spotweb. The error has been logged.</p>";
    echo "<p>Please check the error log at: ' . $errorLogFile . '</p>";
}
';

// Write the wrapper file
file_put_contents($wrapperPath, $wrapperContent);

echo "<div class='info'>
    <p>A debug version of index.php has been created at: <code>index-debug.php</code></p>
    <p>You can use this to see more detailed error information:</p>
    <a href='index-debug.php' class='button' target='_blank'>Access Spotweb (Debug Mode)</a>
</div>";

echo "</body>
</html>";
?>
