<?php
/**
 * Simple check for retrieve.php issues
 */

echo "=== Simple Retrieve Check ===\n\n";

// Check if ownsettings.php exists
if (file_exists(__DIR__ . '/ownsettings.php')) {
    echo "✓ ownsettings.php exists\n";
} else {
    echo "✗ ownsettings.php NOT FOUND\n";
    echo "  You need to create ownsettings.php from ownsettings.php.defaults\n\n";
    exit(1);
}

// Check if dbsettings.inc.php exists
if (file_exists(__DIR__ . '/dbsettings.inc.php')) {
    echo "✓ dbsettings.inc.php exists\n\n";
} else {
    echo "✗ dbsettings.inc.php NOT FOUND\n";
    echo "  Database settings file is missing\n\n";
}

echo "--- Checking Configuration ---\n";

// Include the settings file to check NNTP configuration
// We'll capture any errors
ob_start();
try {
    // Don't load full bootstrap, just check settings syntax
    if (file_exists(__DIR__ . '/ownsettings.php')) {
        $testSettings = [];
        $settings = [];
        
        // Read file content
        $content = file_get_contents(__DIR__ . '/ownsettings.php');
        
        // Check for NNTP configuration
        if (strpos($content, "nntp_hdr") !== false) {
            echo "✓ Found nntp_hdr configuration in ownsettings.php\n";
        } else {
            echo "⚠ WARNING: No nntp_hdr configuration found\n";
            echo "  You need to configure your NNTP server settings\n\n";
        }
        
        if (strpos($content, "hdr_group") !== false) {
            echo "✓ Found hdr_group configuration\n";
        } else {
            echo "⚠ WARNING: No hdr_group (newsgroup) configured\n\n";
        }
        
        // Check for common default values that need changing
        if (strpos($content, "news.example.com") !== false) {
            echo "⚠ WARNING: Found 'news.example.com' - you need to change this to your actual news server\n\n";
        }
    }
} catch (Exception $e) {
    echo "Error checking configuration: " . $e->getMessage() . "\n";
}
ob_end_clean();

echo "\n--- Common Issues ---\n";
echo "1. NNTP Server Not Configured:\n";
echo "   - Edit ownsettings.php\n";
echo "   - Set your news server host, username, and password\n";
echo "   - Set hdr_group (usually 'free.pt' or similar)\n\n";

echo "2. Retriever Already Running:\n";
echo "   - Check if retrieve.php is already running in another process\n";
echo "   - If stuck, try: php retrieve.php --force\n\n";

echo "3. No New Posts Available:\n";
echo "   - Your newsserver might not have any new posts\n";
echo "   - Check last retrieval time in Spotweb interface\n\n";

echo "4. Connection Issues:\n";
echo "   - Verify your newsserver credentials\n";
echo "   - Check if your ISP/firewall blocks NNTP ports (usually 119 or 563)\n";
echo "   - Ensure SSL/TLS settings match your server requirements\n\n";

echo "--- Next Steps ---\n";
echo "To test retrieve manually, access through web interface:\n";
echo "http://localhost:8888/?page=getstream&action=retrieve\n";
echo "(add your API key: &apikey=YOUR_API_KEY)\n\n";

echo "Or run from command line:\n";
echo "/Applications/MAMP/bin/php/php8.2.0/bin/php retrieve.php\n\n";

echo "=== Check Complete ===\n";
