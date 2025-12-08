<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set("error_log", "/Users/venimk/Sites/spotweb/spotweb-error.log");

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
    echo "<p>Please check the error log at: /Users/venimk/Sites/spotweb/spotweb-error.log</p>";
}
