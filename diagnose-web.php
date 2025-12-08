<!DOCTYPE html>
<html>
<head>
    <title>Spotweb Retrieve Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; border-left: 4px solid #4CAF50; padding-left: 10px; }
        .success { color: #4CAF50; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .info { background: #e3f2fd; padding: 10px; border-left: 4px solid #2196F3; margin: 10px 0; }
        .code { background: #f4f4f4; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        .section { margin: 20px 0; padding: 15px; background: #fafafa; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table td { padding: 8px; border-bottom: 1px solid #ddd; }
        table td:first-child { font-weight: bold; width: 200px; }
        .recommendation { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Spotweb Retrieve Diagnostic</h1>
        
        <?php
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        // Initialize
        try {
            require_once __DIR__.'/vendor/autoload.php';
            echo '<div class="section"><span class="success">✓</span> Autoloader loaded successfully</div>';
            
            $bootstrap = new Bootstrap();
            list($settings, $daoFactory, $req) = $bootstrap->boot();
            echo '<div class="section"><span class="success">✓</span> Bootstrap initialized successfully</div>';
            
        } catch (Exception $e) {
            echo '<div class="section"><span class="error">✗</span> Failed to initialize: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<div class="recommendation">Cannot proceed with diagnostics. Check your database configuration in dbsettings.inc.php</div>';
            echo '</div></body></html>';
            exit;
        }
        
        // Check NNTP Configuration
        echo '<h2>📡 NNTP Server Configuration</h2>';
        echo '<div class="section">';
        
        try {
            $nntpHdr = $settings->get('nntp_hdr');
            $nntpNzb = $settings->get('nntp_nzb');
            
            echo '<h3>Header Server (nntp_hdr)</h3>';
            echo '<table>';
            echo '<tr><td>Host:</td><td>' . htmlspecialchars($nntpHdr['host'] ?? 'NOT SET') . '</td></tr>';
            echo '<tr><td>Port:</td><td>' . htmlspecialchars($nntpHdr['port'] ?? 'NOT SET') . '</td></tr>';
            echo '<tr><td>Username:</td><td>' . htmlspecialchars($nntpHdr['user'] ?? 'NOT SET') . '</td></tr>';
            echo '<tr><td>Password:</td><td>' . (empty($nntpHdr['pass']) ? 'NOT SET' : '***SET***') . '</td></tr>';
            echo '<tr><td>Encryption:</td><td>' . htmlspecialchars($nntpHdr['enc'] ?? 'none') . '</td></tr>';
            echo '</table>';
            
            if (empty($nntpHdr['host']) || $nntpHdr['host'] === 'news.example.com') {
                echo '<div class="recommendation">';
                echo '<span class="warning">⚠ WARNING:</span> NNTP header server is not configured!<br>';
                echo 'You need to edit <strong>ownsettings.php</strong> and configure your newsserver settings.';
                echo '</div>';
                $hasConfig = false;
            } else {
                echo '<div class="info"><span class="success">✓</span> NNTP server appears to be configured</div>';
                $hasConfig = true;
            }
            
        } catch (Exception $e) {
            echo '<span class="error">✗</span> Error reading NNTP settings: ' . htmlspecialchars($e->getMessage());
        }
        
        echo '</div>';
        
        // Check Newsgroup Configuration
        echo '<h2>📰 Newsgroup Configuration</h2>';
        echo '<div class="section">';
        
        try {
            $hdrGroup = $settings->get('hdr_group');
            $nzbGroup = $settings->get('nzb_group');
            
            echo '<table>';
            echo '<tr><td>Header Group:</td><td>' . htmlspecialchars($hdrGroup ?: 'NOT SET') . '</td></tr>';
            echo '<tr><td>Binary Group:</td><td>' . htmlspecialchars($nzbGroup ?: 'NOT SET') . '</td></tr>';
            echo '</table>';
            
            if (empty($hdrGroup)) {
                echo '<div class="recommendation">';
                echo '<span class="warning">⚠ WARNING:</span> Header group not configured!<br>';
                echo 'Should typically be something like <strong>free.pt</strong> or your provider\'s spot group.';
                echo '</div>';
                $hasConfig = false;
            } else {
                echo '<div class="info"><span class="success">✓</span> Newsgroup configured: ' . htmlspecialchars($hdrGroup) . '</div>';
            }
            
        } catch (Exception $e) {
            echo '<span class="error">✗</span> Error: ' . htmlspecialchars($e->getMessage());
        }
        
        echo '</div>';
        
        // Check Retriever Status
        echo '<h2>⚙️ Retriever Status</h2>';
        echo '<div class="section">';
        
        try {
            $usenetStateDao = $daoFactory->getUsenetStateDao();
            $isRunning = $usenetStateDao->isRetrieverRunning();
            
            if ($isRunning) {
                echo '<div class="recommendation">';
                echo '<span class="warning">⚠ WARNING:</span> Retriever is marked as RUNNING in database<br>';
                echo 'This prevents new retrieve sessions from starting.<br><br>';
                echo 'If retriever is not actually running, you can:<br>';
                echo '1. Wait for it to finish<br>';
                echo '2. Run from command line: <code>php retrieve.php --force</code><br>';
                echo '3. Clear the running flag manually';
                echo '</div>';
            } else {
                echo '<div class="info"><span class="success">✓</span> Retriever is not currently running</div>';
            }
            
            $lastUpdate = $usenetStateDao->getLastUpdate(Dao_UsenetState::State_Spots);
            $lastArticleNr = $usenetStateDao->getLastArticleNumber(Dao_UsenetState::State_Spots);
            
            echo '<table>';
            echo '<tr><td>Last Retrieval:</td><td>' . ($lastUpdate > 0 ? date('Y-m-d H:i:s', $lastUpdate) : '<span class="warning">NEVER</span>') . '</td></tr>';
            echo '<tr><td>Last Article Number:</td><td>' . ($lastArticleNr ?: '0') . '</td></tr>';
            echo '</table>';
            
        } catch (Exception $e) {
            echo '<span class="error">✗</span> Error: ' . htmlspecialchars($e->getMessage());
        }
        
        echo '</div>';
        
        // Test NNTP Connection
        if (isset($hasConfig) && $hasConfig) {
            echo '<h2>🔌 NNTP Connection Test</h2>';
            echo '<div class="section">';
            
            try {
                echo 'Attempting to connect to ' . htmlspecialchars($nntpHdr['host']) . ':' . htmlspecialchars($nntpHdr['port']) . '...<br>';
                
                $svcNntpText = Services_Nntp_EnginePool::pool($settings, 'hdr');
                
                $hdrGroup = $settings->get('hdr_group');
                if (!empty($hdrGroup)) {
                    echo 'Selecting newsgroup: ' . htmlspecialchars($hdrGroup) . '...<br>';
                    $groupInfo = $svcNntpText->selectGroup($hdrGroup);
                    
                    echo '<div class="info">';
                    echo '<span class="success">✓</span> Successfully connected and selected group!<br>';
                    echo '<table>';
                    echo '<tr><td>First Message:</td><td>' . number_format($groupInfo['first']) . '</td></tr>';
                    echo '<tr><td>Last Message:</td><td>' . number_format($groupInfo['last']) . '</td></tr>';
                    echo '<tr><td>Total Messages:</td><td>' . number_format($groupInfo['last'] - $groupInfo['first']) . '</td></tr>';
                    echo '</table>';
                    echo '</div>';
                    
                    // Check if there are new messages to retrieve
                    if ($lastArticleNr >= $groupInfo['last']) {
                        echo '<div class="recommendation">';
                        echo '<span class="warning">ℹ</span> All available posts have been retrieved<br>';
                        echo 'Last retrieved article: <strong>' . number_format($lastArticleNr) . '</strong><br>';
                        echo 'Latest on server: <strong>' . number_format($groupInfo['last']) . '</strong><br>';
                        echo '<br><strong>There are no new posts to retrieve at this time.</strong>';
                        echo '</div>';
                    } else {
                        $newPosts = $groupInfo['last'] - $lastArticleNr;
                        echo '<div class="info" style="background: #c8e6c9;">';
                        echo '<span class="success">✓</span> Approximately <strong>' . number_format($newPosts) . '</strong> new posts available to retrieve!';
                        echo '</div>';
                    }
                }
                
            } catch (Exception $e) {
                echo '<div class="recommendation">';
                echo '<span class="error">✗</span> NNTP Connection FAILED<br>';
                echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '<br>';
                echo '<strong>Error Code:</strong> ' . $e->getCode() . '<br><br>';
                echo '<strong>Common causes:</strong><br>';
                echo '• Incorrect server hostname or port<br>';
                echo '• Wrong username or password<br>';
                echo '• Server requires SSL/TLS but encryption not enabled<br>';
                echo '• Firewall blocking connection<br>';
                echo '• Server is down or unreachable';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        // Database Status
        echo '<h2>💾 Database Status</h2>';
        echo '<div class="section">';
        
        try {
            $connection = $daoFactory->getConnection();
            $spotCount = $connection->singleQuery("SELECT COUNT(*) as cnt FROM spots");
            
            echo '<div class="info"><span class="success">✓</span> Database connection successful</div>';
            echo '<table>';
            echo '<tr><td>Total Spots in Database:</td><td><strong>' . number_format($spotCount) . '</strong></td></tr>';
            echo '</table>';
            
        } catch (Exception $e) {
            echo '<span class="error">✗</span> Database error: ' . htmlspecialchars($e->getMessage());
        }
        
        echo '</div>';
        
        // Recommendations
        echo '<h2>💡 Recommendations</h2>';
        echo '<div class="section">';
        
        if (!isset($hasConfig) || !$hasConfig) {
            echo '<div class="recommendation">';
            echo '<strong>❗ CRITICAL: Configure your NNTP server settings</strong><br><br>';
            echo 'Edit <strong>ownsettings.php</strong> and set:<br>';
            echo '<div class="code">';
            echo "\$settings['nntp_hdr']['host'] = 'your.newsserver.com';<br>";
            echo "\$settings['nntp_hdr']['user'] = 'your_username';<br>";
            echo "\$settings['nntp_hdr']['pass'] = 'your_password';<br>";
            echo "\$settings['hdr_group'] = 'free.pt'; // or your provider's spot group";
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="info">';
            echo 'Configuration looks good! To retrieve new posts:<br><br>';
            echo '<strong>From command line:</strong><br>';
            echo '<div class="code">/Applications/MAMP/bin/php/php8.2.0/bin/php retrieve.php</div>';
            echo '<strong>Or if marked as running:</strong><br>';
            echo '<div class="code">/Applications/MAMP/bin/php/php8.2.0/bin/php retrieve.php --force</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        ?>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #999; text-align: center;">
            Diagnostic completed at <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</body>
</html>
