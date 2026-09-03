#!/usr/bin/env php
<?php
/**
 * Configure SpotWeb NZB download integration for a user.
 *
 * Examples:
 *   # Save NZBs to disk (works without SABnzbd/NZBGet)
 *   php bin/configure-nzb.php --user=VenimK --mode=save --dir=/Users/venimk/Sites/spotweb/nzb
 *
 *   # Push to SABnzbd over HTTP
 *   php bin/configure-nzb.php --user=VenimK --mode=push-sabnzbd \
 *     --sab-url=http://127.0.0.1:8080/sabnzbd/ --sab-apikey=YOURKEY
 *
 *   # NZBGet
 *   php bin/configure-nzb.php --user=VenimK --mode=nzbget \
 *     --nzbget-host=127.0.0.1 --nzbget-port=6789 --nzbget-user=nzbget --nzbget-pass=tegbzn6789
 *
 *   # Disable integration
 *   php bin/configure-nzb.php --user=VenimK --mode=disable
 *
 *   # Show current config
 *   php bin/configure-nzb.php --user=VenimK --show
 */

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "configure-nzb.php can only be run from the console\n");
    exit(2);
}

$opts = getopt('', [
    'user:',
    'mode:',
    'dir:',
    'sab-url:',
    'sab-apikey:',
    'sab-user:',
    'sab-pass:',
    'nzbget-host:',
    'nzbget-port:',
    'nzbget-user:',
    'nzbget-pass:',
    'nzbget-ssl',
    'prepare:',
    'show',
    'help',
]);

if (isset($opts['help']) || empty($opts['user'])) {
    echo <<<TXT
Usage: php bin/configure-nzb.php --user=NAME [options]

  --user=NAME           Username (required)
  --show                Print current NZB handling prefs and exit
  --mode=MODE           disable | save | push-sabnzbd | client-sabnzbd | nzbget | runcommand
  --dir=PATH            Local directory for save/runcommand (created if missing)
  --prepare=merge|zip   Multi-NZB prepare action (default: merge)

  SABnzbd:
  --sab-url=URL         e.g. http://127.0.0.1:8080/sabnzbd/
  --sab-apikey=KEY
  --sab-user=USER       optional HTTP auth
  --sab-pass=PASS

  NZBGet:
  --nzbget-host=HOST
  --nzbget-port=PORT    default 6789
  --nzbget-user=USER
  --nzbget-pass=PASS
  --nzbget-ssl

TXT;
    exit(isset($opts['help']) ? 0 : 1);
}

$root = dirname(__DIR__);
require_once $root.'/vendor/autoload.php';

$bootstrap = new Bootstrap();
list($settings, $daoFactory, $req) = $bootstrap->boot();
$userDao = $daoFactory->getUserDao();

$userId = $userDao->findUserIdForName($opts['user']);
if (empty($userId)) {
    fwrite(STDERR, "User not found: {$opts['user']}\n");
    exit(1);
}

$user = $userDao->getUser((int) $userId);
if ($user === false) {
    fwrite(STDERR, "Could not load user id {$userId}\n");
    exit(1);
}

$prefs = $user['prefs'];
if (!isset($prefs['nzbhandling']) || !is_array($prefs['nzbhandling'])) {
    $prefs['nzbhandling'] = [
        'action'         => 'disable',
        'local_dir'      => $root.'/nzb',
        'prepare_action' => 'merge',
        'command'        => '',
        'sabnzbd'        => ['url' => '', 'apikey' => '', 'username' => '', 'password' => ''],
        'nzbget'         => ['host' => '', 'port' => '6789', 'ssl' => false, 'username' => '', 'password' => '', 'timeout' => 30],
        'nzbvortex'      => ['host' => '', 'port' => '', 'apikey' => ''],
    ];
}

if (isset($opts['show'])) {
    $safe = $prefs['nzbhandling'];
    if (!empty($safe['sabnzbd']['apikey'])) {
        $safe['sabnzbd']['apikey'] = substr($safe['sabnzbd']['apikey'], 0, 4).'…';
    }
    if (!empty($safe['sabnzbd']['password'])) {
        $safe['sabnzbd']['password'] = '***';
    }
    if (!empty($safe['nzbget']['password'])) {
        $safe['nzbget']['password'] = '***';
    }
    echo "User: {$user['username']} (id {$user['userid']})\n";
    echo json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    exit(0);
}

if (empty($opts['mode'])) {
    fwrite(STDERR, "Missing --mode (or use --show)\n");
    exit(1);
}

$mode = $opts['mode'];
$allowed = ['disable', 'save', 'push-sabnzbd', 'client-sabnzbd', 'nzbget', 'runcommand', 'nzbvortex'];
if (!in_array($mode, $allowed, true)) {
    fwrite(STDERR, "Unknown mode: {$mode}\n");
    exit(1);
}

$prefs['nzbhandling']['action'] = $mode;
$prefs['nzbhandling']['prepare_action'] = $opts['prepare'] ?? ($prefs['nzbhandling']['prepare_action'] ?? 'merge');

// Always enable NZB UI affordances when wiring a real handler
if ($mode !== 'disable') {
    $prefs['show_nzbbutton'] = true;
    $prefs['show_multinzb'] = true;
    $prefs['keep_downloadlist'] = true;
}

switch ($mode) {
    case 'save':
    case 'runcommand':
        $dir = $opts['dir'] ?? ($prefs['nzbhandling']['local_dir'] ?: $root.'/nzb');
        $dir = rtrim($dir, "/\\");
        if ($dir === '') {
            fwrite(STDERR, "--dir is required for mode {$mode}\n");
            exit(1);
        }
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            fwrite(STDERR, "Could not create directory: {$dir}\n");
            exit(1);
        }
        if (!is_writable($dir)) {
            fwrite(STDERR, "Directory is not writable: {$dir}\n");
            exit(1);
        }
        $prefs['nzbhandling']['local_dir'] = $dir;
        break;

    case 'push-sabnzbd':
    case 'client-sabnzbd':
        $url = $opts['sab-url'] ?? ($prefs['nzbhandling']['sabnzbd']['url'] ?? '');
        $key = $opts['sab-apikey'] ?? ($prefs['nzbhandling']['sabnzbd']['apikey'] ?? '');
        if ($url === '' || $key === '') {
            fwrite(STDERR, "SABnzbd modes require --sab-url and --sab-apikey\n");
            exit(1);
        }
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        $prefs['nzbhandling']['sabnzbd']['url'] = $url;
        $prefs['nzbhandling']['sabnzbd']['apikey'] = $key;
        if (isset($opts['sab-user'])) {
            $prefs['nzbhandling']['sabnzbd']['username'] = $opts['sab-user'];
        }
        if (isset($opts['sab-pass'])) {
            $prefs['nzbhandling']['sabnzbd']['password'] = $opts['sab-pass'];
        }
        break;

    case 'nzbget':
        $host = $opts['nzbget-host'] ?? ($prefs['nzbhandling']['nzbget']['host'] ?? '');
        $port = $opts['nzbget-port'] ?? ($prefs['nzbhandling']['nzbget']['port'] ?? '6789');
        if ($host === '') {
            fwrite(STDERR, "nzbget mode requires --nzbget-host\n");
            exit(1);
        }
        $prefs['nzbhandling']['nzbget']['host'] = $host;
        $prefs['nzbhandling']['nzbget']['port'] = (string) $port;
        $prefs['nzbhandling']['nzbget']['ssl'] = isset($opts['nzbget-ssl']);
        if (isset($opts['nzbget-user'])) {
            $prefs['nzbhandling']['nzbget']['username'] = $opts['nzbget-user'];
        }
        if (isset($opts['nzbget-pass'])) {
            $prefs['nzbhandling']['nzbget']['password'] = $opts['nzbget-pass'];
        }
        $prefs['nzbhandling']['nzbget']['timeout'] = 30;
        break;
}

$user['prefs'] = $prefs;
$userDao->setUser($user);

echo "Updated NZB handling for {$user['username']}:\n";
echo "  mode: {$prefs['nzbhandling']['action']}\n";
if (in_array($mode, ['save', 'runcommand'], true)) {
    echo "  dir:  {$prefs['nzbhandling']['local_dir']}\n";
}
if (in_array($mode, ['push-sabnzbd', 'client-sabnzbd'], true)) {
    echo "  sab:  {$prefs['nzbhandling']['sabnzbd']['url']}\n";
}
if ($mode === 'nzbget') {
    echo "  nzbget: {$prefs['nzbhandling']['nzbget']['host']}:{$prefs['nzbhandling']['nzbget']['port']}\n";
}
echo "Done. Reload SpotWeb and use the download button (or press d).\n";
exit(0);
