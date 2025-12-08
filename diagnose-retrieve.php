<?php
/**
 * Diagnostic script to identify why retrieve.php is not getting new posts
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Spotweb Retrieve Diagnostic ===\n\n";

// Check if we can load the bootstrap
try {
    require_once __DIR__.'/vendor/autoload.php';
    echo "✓ Autoloader loaded successfully\n";
    
    $bootstrap = new Bootstrap();
    list($settings, $daoFactory, $req) = $bootstrap->boot();
    echo "✓ Bootstrap initialized successfully\n\n";
} catch (Exception $e) {
    echo "✗ Failed to initialize bootstrap: " . $e->getMessage() . "\n";
    exit(1);
}

// Check NNTP Server Settings
echo "--- NNTP Server Configuration ---\n";
try {
    $nntpHdr = $settings->get('nntp_hdr');
    $nntpNzb = $settings->get('nntp_nzb');
    
    echo "Header Server (nntp_hdr):\n";
    echo "  Host: " . ($nntpHdr['host'] ?? 'NOT SET') . "\n";
    echo "  Port: " . ($nntpHdr['port'] ?? 'NOT SET') . "\n";
    echo "  User: " . ($nntpHdr['user'] ?? 'NOT SET') . "\n";
    echo "  Password: " . (empty($nntpHdr['pass']) ? 'NOT SET' : '***SET***') . "\n";
    echo "  Encryption: " . ($nntpHdr['enc'] ?? 'none') . "\n\n";
    
    echo "Binary Server (nntp_nzb):\n";
    echo "  Host: " . ($nntpNzb['host'] ?? 'NOT SET') . "\n";
    echo "  Port: " . ($nntpNzb['port'] ?? 'NOT SET') . "\n";
    echo "  User: " . ($nntpNzb['user'] ?? 'NOT SET') . "\n";
    echo "  Password: " . (empty($nntpNzb['pass']) ? 'NOT SET' : '***SET***') . "\n";
    echo "  Encryption: " . ($nntpNzb['enc'] ?? 'none') . "\n\n";
    
    // Check if servers are configured
    if (empty($nntpHdr['host']) || $nntpHdr['host'] === 'news.example.com') {
        echo "⚠ WARNING: NNTP header server is not configured!\n";
        echo "  Please configure your NNTP server in ownsettings.php\n\n";
    }
} catch (Exception $e) {
    echo "✗ Error reading NNTP settings: " . $e->getMessage() . "\n\n";
}

// Check Newsgroup Settings
echo "--- Newsgroup Configuration ---\n";
try {
    $hdrGroup = $settings->get('hdr_group');
    $nzbGroup = $settings->get('nzb_group');
    
    echo "Header Group: " . ($hdrGroup ?: 'NOT SET') . "\n";
    echo "Binary Group: " . ($nzbGroup ?: 'NOT SET') . "\n\n";
    
    if (empty($hdrGroup)) {
        echo "⚠ WARNING: Header group not configured!\n";
        echo "  Should typically be something like 'free.pt'\n\n";
    }
} catch (Exception $e) {
    echo "✗ Error reading newsgroup settings: " . $e->getMessage() . "\n\n";
}

// Check Retrieval Settings
echo "--- Retrieval Settings ---\n";
try {
    echo "Retrieve Full Spots: " . ($settings->get('retrieve_full') ? 'YES' : 'NO') . "\n";
    echo "Retrieve Comments: " . ($settings->get('retrieve_comments') ? 'YES' : 'NO') . "\n";
    echo "Retrieve Reports: " . ($settings->get('retrieve_reports') ? 'YES' : 'NO') . "\n";
    echo "Retention Days: " . $settings->get('retention') . "\n";
    echo "Retention Type: " . $settings->get('retentiontype') . "\n\n";
} catch (Exception $e) {
    echo "✗ Error reading retrieval settings: " . $e->getMessage() . "\n\n";
}

// Check if retriever is already running
echo "--- Retriever Status ---\n";
try {
    $usenetStateDao = $daoFactory->getUsenetStateDao();
    $isRunning = $usenetStateDao->isRetrieverRunning();
    
    if ($isRunning) {
        echo "⚠ WARNING: Retriever is marked as RUNNING in database\n";
        echo "  This prevents new retrieve sessions from starting.\n";
        echo "  If retriever is not actually running, you can:\n";
        echo "  1. Wait for it to finish\n";
        echo "  2. Run: php retrieve.php --force\n";
        echo "  3. Manually reset the database flag\n\n";
    } else {
        echo "✓ Retriever is not currently running\n\n";
    }
    
    $lastUpdate = $usenetStateDao->getLastUpdate(Dao_UsenetState::State_Spots);
    echo "Last Spot Retrieval: " . ($lastUpdate > 0 ? date('Y-m-d H:i:s', $lastUpdate) : 'NEVER') . "\n";
    
    $lastArticleNr = $usenetStateDao->getLastArticleNumber(Dao_UsenetState::State_Spots);
    echo "Last Article Number: " . ($lastArticleNr ?: '0') . "\n\n";
} catch (Exception $e) {
    echo "✗ Error checking retriever status: " . $e->getMessage() . "\n\n";
}

// Test NNTP Connection
echo "--- Testing NNTP Connection ---\n";
try {
    if (!empty($nntpHdr['host']) && $nntpHdr['host'] !== 'news.example.com') {
        echo "Attempting to connect to " . $nntpHdr['host'] . ":" . $nntpHdr['port'] . "...\n";
        
        $svcNntpText = Services_Nntp_EnginePool::pool($settings, 'hdr');
        
        // Try to select the newsgroup
        $hdrGroup = $settings->get('hdr_group');
        if (!empty($hdrGroup)) {
            echo "Selecting newsgroup: " . $hdrGroup . "...\n";
            $groupInfo = $svcNntpText->selectGroup($hdrGroup);
            
            echo "✓ Successfully connected and selected group!\n";
            echo "  First Message: " . $groupInfo['first'] . "\n";
            echo "  Last Message: " . $groupInfo['last'] . "\n";
            echo "  Total Messages: " . ($groupInfo['last'] - $groupInfo['first']) . "\n\n";
        } else {
            echo "⚠ Skipping group selection - no header group configured\n\n";
        }
    } else {
        echo "⚠ Skipping connection test - NNTP server not configured\n\n";
    }
} catch (Exception $e) {
    echo "✗ NNTP Connection FAILED: " . $e->getMessage() . "\n";
    echo "  Error Code: " . $e->getCode() . "\n\n";
    echo "Common causes:\n";
    echo "  - Incorrect server hostname or port\n";
    echo "  - Wrong username or password\n";
    echo "  - Server requires SSL/TLS but encryption not enabled\n";
    echo "  - Firewall blocking connection\n";
    echo "  - Server is down or unreachable\n\n";
}

// Check database connectivity
echo "--- Database Status ---\n";
try {
    $spotDao = $daoFactory->getSpotDao();
    $connection = $daoFactory->getConnection();
    
    echo "✓ Database connection successful\n";
    
    // Get spot count
    $spotCount = $connection->singleQuery("SELECT COUNT(*) as cnt FROM spots");
    echo "Total Spots in Database: " . $spotCount . "\n\n";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n\n";
}

// Provide recommendations
echo "--- Recommendations ---\n";
if (empty($nntpHdr['host']) || $nntpHdr['host'] === 'news.example.com') {
    echo "❗ CRITICAL: You need to configure your NNTP server settings\n";
    echo "   Edit ownsettings.php and set:\n";
    echo "   - \$settings['nntp_hdr']['host'] = 'your.newsserver.com';\n";
    echo "   - \$settings['nntp_hdr']['user'] = 'your_username';\n";
    echo "   - \$settings['nntp_hdr']['pass'] = 'your_password';\n";
    echo "   - \$settings['hdr_group'] = 'free.pt'; (or your provider's spot group)\n\n";
} elseif (isset($groupInfo) && $lastArticleNr >= $groupInfo['last']) {
    echo "ℹ All available posts have been retrieved\n";
    echo "  Last retrieved article: " . $lastArticleNr . "\n";
    echo "  Latest on server: " . $groupInfo['last'] . "\n";
    echo "  There are no new posts to retrieve.\n\n";
} else {
    echo "✓ Configuration looks good. Try running:\n";
    echo "  php retrieve.php\n";
    echo "  or if marked as running:\n";
    echo "  php retrieve.php --force\n\n";
}

echo "=== Diagnostic Complete ===\n";
