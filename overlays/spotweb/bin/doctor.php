#!/usr/bin/env php
<?php
/**
 * SpotWeb health check / doctor.
 *
 * Usage:
 *   php bin/doctor.php
 *   php bin/doctor.php --nntp          # also try NNTP connect (slow)
 *   php bin/doctor.php --json          # machine-readable output
 *   php bin/doctor.php --quiet         # exit code only (0=ok, 1=warn, 2=fail)
 */

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "doctor.php can only be run from the console\n");
    exit(2);
}

$opts = getopt('', ['nntp', 'json', 'quiet', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php bin/doctor.php [--nntp] [--json] [--quiet]\n";
    exit(0);
}

$wantNntp = isset($opts['nntp']);
$asJson = isset($opts['json']);
$quiet = isset($opts['quiet']);

$root = dirname(__DIR__);
$checks = [];
$worst = 0; // 0 ok, 1 warn, 2 fail

$add = function (string $id, string $status, string $message, array $meta = []) use (&$checks, &$worst) {
    $level = ['ok' => 0, 'warn' => 1, 'fail' => 2][$status] ?? 1;
    $worst = max($worst, $level);
    $checks[] = array_merge(['id' => $id, 'status' => $status, 'message' => $message], $meta);
};

// --- PHP ---
$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
$add('php_version', $phpOk ? 'ok' : 'fail', 'PHP '.PHP_VERSION, ['min' => '8.0.0']);

$requiredExt = ['pdo', 'json', 'xml', 'mbstring', 'openssl', 'curl'];
$missing = [];
foreach ($requiredExt as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}
$add(
    'php_extensions',
    empty($missing) ? 'ok' : 'fail',
    empty($missing) ? 'Required extensions loaded' : 'Missing extensions: '.implode(', ', $missing)
);

// --- Paths ---
$paths = [
    'cache' => $root.'/cache',
    'logs'  => $root.'/logs',
];
foreach ($paths as $name => $path) {
    if (!is_dir($path)) {
        $add('path_'.$name, 'fail', $path.' does not exist');
        continue;
    }
    $writable = is_writable($path);
    $add('path_'.$name, $writable ? 'ok' : 'fail', $path.($writable ? ' is writable' : ' is NOT writable'));
}

$dbsettingsFile = $root.'/dbsettings.inc.php';
if (!is_readable($dbsettingsFile)) {
    $add('dbsettings', 'fail', 'dbsettings.inc.php missing or unreadable');
} else {
    $perms = substr(sprintf('%o', fileperms($dbsettingsFile)), -4);
    $add(
        'dbsettings',
        (int) $perms > 640 ? 'warn' : 'ok',
        'dbsettings.inc.php present (mode '.$perms.')',
        ['hint' => 'Prefer 600 or 640']
    );
}

// --- Bootstrap / DB ---
try {
    require_once $root.'/vendor/autoload.php';
    $bootstrap = new Bootstrap();
    list($settings, $daoFactory, $req) = $bootstrap->boot();
    $db = $daoFactory->getConnection();
    $add('bootstrap', 'ok', 'Bootstrap + database connection OK');
} catch (Throwable $e) {
    $add('bootstrap', 'fail', 'Boot failed: '.$e->getMessage());
    emit_and_exit($checks, $worst, $asJson, $quiet);
}

// Spot counts
try {
    $spotCount = (int) $db->singleQuery('SELECT COUNT(*) FROM spots');
    $add('spots', $spotCount > 0 ? 'ok' : 'warn', $spotCount.' spots in database');
} catch (Throwable $e) {
    $add('spots', 'fail', 'Could not count spots: '.$e->getMessage());
    $spotCount = 0;
}

// Usenet state / last retrieve
try {
    $lastRetrieved = (int) $daoFactory->getUsenetStateDao()->getLastUpdate(Dao_UsenetState::State_Spots);
    $nowRunning = (int) $db->singleQuery(
        "SELECT nowrunning FROM usenetstate WHERE infotype = 'Spots' LIMIT 1"
    );
    if ($nowRunning > 0) {
        $age = time() - $nowRunning;
        $stale = $age > 7200;
        $add(
            'retrieve_running',
            $stale ? 'warn' : 'ok',
            'Retrieve marked running since '.date('c', $nowRunning).' ('.$age.'s ago)',
            ['hint' => $stale ? 'Stale lock? Clear usenetstate.nowrunning if retrieve crashed' : null]
        );
    } else {
        $add('retrieve_running', 'ok', 'No retrieve currently marked as running');
    }

    if ($lastRetrieved > 0) {
        $ageH = (time() - $lastRetrieved) / 3600;
        $status = $ageH > 24 ? 'warn' : 'ok';
        $add(
            'last_retrieve',
            $status,
            'Last spots retrieve: '.date('c', $lastRetrieved).sprintf(' (%.1fh ago)', $ageH)
        );
    } else {
        $add('last_retrieve', 'warn', 'No successful retrieve recorded yet (lastretrieved=0)');
    }
} catch (Throwable $e) {
    $add('last_retrieve', 'fail', 'Usenet state error: '.$e->getMessage());
}

// NNTP config
foreach (['nntp_hdr' => 'Header NNTP', 'nntp_nzb' => 'NZB NNTP'] as $key => $label) {
    try {
        $nntp = $settings->get($key);
        $host = is_array($nntp) ? trim((string) ($nntp['host'] ?? '')) : '';
        if ($host === '') {
            $add($key, 'fail', $label.' host not configured');
        } else {
            $port = $nntp['port'] ?? '?';
            $enc = $nntp['enc'] ?? 'plain';
            $add($key, 'ok', $label.': '.$host.':'.$port.' ('.$enc.')');
        }
    } catch (Throwable $e) {
        $add($key, 'fail', $label.' setting error: '.$e->getMessage());
    }
}

if ($wantNntp) {
    try {
        $nntp = $settings->get('nntp_hdr');
        $engine = Services_Nntp_EnginePool::pool($settings, 'hdr');
        // selectGroup is heavy; just validate we can talk to the server via get overview of last state
        $engine->selectGroup($settings->get('hdr_group'));
        $add('nntp_connect', 'ok', 'NNTP header server accepted group '.$settings->get('hdr_group'));
    } catch (Throwable $e) {
        $add('nntp_connect', 'fail', 'NNTP connect/group failed: '.$e->getMessage());
    }
}

// Download integration (per-user prefs — SpotWeb stores this on the user, not globally)
try {
    $configured = [];
    $users = $db->arrayQuery('SELECT id, username FROM users WHERE id > 1 AND deleted = 0 ORDER BY id');
    foreach ($users as $u) {
        $full = $daoFactory->getUserDao()->getUser((int) $u['id']);
        if ($full === false) {
            continue;
        }
        $action = $full['prefs']['nzbhandling']['action'] ?? 'disable';
        if (!$action || $action === 'disable') {
            continue;
        }
        $detail = $u['username'].' → '.$action;
        if ($action === 'save' || $action === 'runcommand') {
            $dir = $full['prefs']['nzbhandling']['local_dir'] ?? '';
            $writable = ($dir !== '' && is_dir($dir) && is_writable($dir));
            $detail .= ' ('.$dir.($writable ? ', writable' : ', MISSING/NOT WRITABLE').')';
            if (!$writable) {
                $add('nzbhandling_dir_'.$u['id'], 'fail', 'NZB save dir problem for '.$u['username'].': '.$dir);
            }
        }
        if ($action === 'push-sabnzbd' || $action === 'client-sabnzbd') {
            $sabUrl = $full['prefs']['nzbhandling']['sabnzbd']['url'] ?? '';
            $sabKey = $full['prefs']['nzbhandling']['sabnzbd']['apikey'] ?? '';
            if ($sabUrl === '' || $sabKey === '') {
                $add('nzbhandling_sab_'.$u['id'], 'fail', 'SABnzbd URL/API key incomplete for '.$u['username']);
            } else {
                $detail .= ' @ '.$sabUrl;
            }
        }
        if ($action === 'nzbget') {
            $host = $full['prefs']['nzbhandling']['nzbget']['host'] ?? '';
            $port = $full['prefs']['nzbhandling']['nzbget']['port'] ?? '';
            $detail .= ' @ '.$host.':'.$port;
            if ($host === '') {
                $add('nzbhandling_nzbget_'.$u['id'], 'fail', 'NZBGet host empty for '.$u['username']);
            }
        }
        $configured[] = $detail;
    }
    if ($configured) {
        $add('nzbhandling', 'ok', 'NZB handling: '.implode('; ', $configured));
    } else {
        $add(
            'nzbhandling',
            'warn',
            'NZB download integration not configured for any user',
            ['hint' => 'php bin/configure-nzb.php --user=NAME --mode=save --dir=...  OR Preferences → NZB handling']
        );
    }
} catch (Throwable $e) {
    $add('nzbhandling', 'warn', 'Could not read nzbhandling: '.$e->getMessage());
}

// Users / retrieve rights
try {
    $adminId = defined('SPOTWEB_ADMIN_USERID') ? SPOTWEB_ADMIN_USERID : 2;
    $users = $db->arrayQuery('SELECT id, username, apikey FROM users WHERE deleted = 0 ORDER BY id');
    $retrieveCapable = 0;
    foreach ($users as $u) {
        $uid = (int) $u['id'];
        if ($uid <= $adminId) {
            continue;
        }
        $perms = $db->arrayQuery(
            'SELECT 1 FROM usergroups ug
             INNER JOIN grouppermissions gp ON gp.groupid = ug.groupid
             WHERE ug.userid = :uid AND gp.permissionid = :perm AND (gp.objectid = \'\' OR gp.objectid IS NULL) AND gp.deny = 0
             LIMIT 1',
            [
                ':uid'  => [$uid, PDO::PARAM_INT],
                ':perm' => [SpotSecurity::spotsec_retrieve_spots, PDO::PARAM_INT],
            ]
        );
        if (!empty($perms) && !empty($u['apikey'])) {
            $retrieveCapable++;
            $add(
                'user_retrieve_'.$uid,
                'ok',
                'User '.$u['username'].' (id '.$uid.') can web-retrieve via API key'
            );
        }
    }
    if ($retrieveCapable === 0) {
        $add(
            'user_retrieve',
            'warn',
            'No non-admin user with retrieve permission + API key',
            ['hint' => 'Use a normal admin-group user (not built-in admin) or CLI retrieve']
        );
    }
    $add(
        'admin_api',
        'ok',
        'Built-in admin (id '.$adminId.') cannot use web retrieve API by design — use CLI or another user'
    );
} catch (Throwable $e) {
    $add('users', 'warn', 'User permission check failed: '.$e->getMessage());
}

// Custom theme extensions
$customLoader = $root.'/custom/includes/theme-loader.inc.php';
$add(
    'custom_themes',
    file_exists($customLoader) ? 'ok' : 'warn',
    file_exists($customLoader)
        ? 'custom/ theme extensions present'
        : 'custom/ theme loader not found (optional)'
);

// retrieve.php present
$add(
    'retrieve_script',
    is_readable($root.'/retrieve.php') ? 'ok' : 'fail',
    is_readable($root.'/retrieve.php') ? 'retrieve.php available for cron/CLI' : 'retrieve.php missing'
);

// PHP binary hint for cron
$phpBin = PHP_BINARY ?: 'php';
$add('php_binary', 'ok', 'CLI binary: '.$phpBin);

emit_and_exit($checks, $worst, $asJson, $quiet);

/**
 * @param list<array<string,mixed>> $checks
 */
function emit_and_exit(array $checks, int $worst, bool $asJson, bool $quiet): void
{
    if ($asJson) {
        echo json_encode([
            'status' => ['ok', 'warn', 'fail'][$worst] ?? 'unknown',
            'exit_code' => $worst,
            'checks' => $checks,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        exit($worst);
    }

    if (!$quiet) {
        $icons = ['ok' => '✓', 'warn' => '!', 'fail' => '✗'];
        echo "SpotWeb doctor\n";
        echo str_repeat('=', 60)."\n";
        foreach ($checks as $c) {
            $icon = $icons[$c['status']] ?? '?';
            echo sprintf("[%s] %-18s %s\n", $icon, $c['id'], $c['message']);
            if (!empty($c['hint'])) {
                echo '      → '.$c['hint']."\n";
            }
        }
        echo str_repeat('=', 60)."\n";
        $label = ['OK', 'WARNINGS', 'FAILURES'][$worst] ?? 'UNKNOWN';
        echo 'Overall: '.$label." (exit {$worst})\n";
        if ($worst > 0) {
            echo "Tip: php bin/doctor.php --nntp  # also test NNTP connectivity\n";
            echo "     php retrieve.php           # run retrieve from CLI\n";
            echo "     bin/retrieve-cron.sh       # cron-friendly wrapper\n";
        }
    }

    exit($worst);
}
