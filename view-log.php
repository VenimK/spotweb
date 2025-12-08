<?php
header("Content-Type: text/plain");
$logFile = __DIR__ . "/install-errors.log";
if (file_exists($logFile)) {
    echo file_get_contents($logFile);
} else {
    echo "Log file not found.";
}
