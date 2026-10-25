<?php
/**
 * Sends an NZBGeek release to the user's configured NZB handler
 * (SABnzbd, NZBGet, NZBVortex, save to disk or run a command).
 *
 * Called by custom/js/nzbgeek.js. Boots Spotweb itself so the normal
 * session, permission checks and NZB handling preferences apply, then
 * fetches the NZB from NZBGeek server-side and hands it to Spotweb's
 * own handler, exactly like Spotweb's download buttons do.
 *
 * POST only, with an "X-Requested-With: spotweb" header (a cross-site
 * form cannot set it), parameters: id (NZBGeek guid), title, cat.
 */
$root = dirname(__DIR__, 2);
require dirname(__DIR__) . '/includes/tool-session.inc.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'spotweb') {
    tool_out(['error' => 'Invalid request.'], 400);
}

$id = (string) ($_POST['id'] ?? '');
if (!preg_match('/^[a-f0-9]{16,64}$/i', $id)) {
    tool_out(['error' => 'Invalid NZBGeek release id.'], 400);
}
/* The title ends up in file names and, for "run command", in a shell command */
$title = preg_replace('/[^A-Za-z0-9._\-()\[\]+ ]/', '.', mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 200));
$title = $title !== '' ? $title : 'NZBGeek.' . $id;
$newznabCat = (int) ($_POST['cat'] ?? 0);

chdir($root);
require_once $root . '/vendor/autoload.php';

try {
    SpotTranslation::initialize('en_US');
    $bootstrap = new Bootstrap();
    [$settings, $daoFactory] = $bootstrap->boot();
    $currentSession = (new Services_User_Authentication($daoFactory, $settings))->useOrStartSession(false);
    if ($currentSession === false) {
        tool_out(['error' => 'A Spotweb login is required.'], 401);
    }

    $prefs = $currentSession['user']['prefs'];
    $nzbHandling = $prefs['nzbhandling'] ?? ['action' => 'disable'];
    $action = (string) ($nzbHandling['action'] ?? 'disable');
    if (!isset(TOOL_SEND_HANDLERS[$action])) {
        tool_out(['error' => 'Your NZB handling setting cannot receive NZBGeek releases.'], 400);
    }
    $currentSession['security']->fatalPermCheck(SpotSecurity::spotsec_retrieve_nzb, '');
    $currentSession['security']->fatalPermCheck(SpotSecurity::spotsec_download_integration, $action);

    $apiKey = tool_api_key($prefs, 'nzbgeek_api_key', 'nzbgeek.php', 'api_key');
    if ($apiKey === '') {
        tool_out(['error' => 'No NZBGeek API key: enter yours under Change preferences.'], 400);
    }
    $cfgFile = dirname(__DIR__) . '/config/nzbgeek.php';
    $config = is_file($cfgFile) ? include $cfgFile : [];
    $baseUrl = rtrim((string) (is_array($config) ? ($config['base_url'] ?? '') : ''), '/') ?: 'https://api.nzbgeek.info';

    /* Fetch the NZB from NZBGeek */
    $ch = curl_init($baseUrl . '/api?t=get&id=' . rawurlencode($id) . '&apikey=' . rawurlencode($apiKey));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'SpotwebNzbGeek/1.0 (+https://github.com/VenimK/spotweb)',
    ]);
    $nzb = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (!is_string($nzb) || $code !== 200 || stripos($nzb, '<nzb') === false) {
        tool_out(['error' => 'Could not download the NZB from NZBGeek.'], 502);
    }

    /*
     * Spotweb handlers only need a title and Spotweb categories (used
     * for the SABnzbd/NZBGet category mapping), so map the Newznab
     * category: 2000 movies / 5000 TV -> image (film/series),
     * 3000 audio -> sound, 1000 console -> games, 4000 PC -> apps.
     */
    $group = intdiv($newznabCat, 1000);
    $spotCat = [1 => 2, 2 => 0, 3 => 1, 4 => 3, 5 => 0][$group] ?? 0;
    $fullSpot = [
        'title' => $title,
        'messageid' => 'nzbgeek-' . $id,
        'category' => $spotCat,
        'subcata' => '',
        'subcatb' => '',
        'subcatc' => '',
        /* d11 is the "TV" genre Spotweb maps to the tv download category */
        'subcatd' => $group === 5 ? 'd11|' : '',
        'subcatz' => $spotCat === 0 ? ($group === 5 ? 'z1|' : 'z0|') : '',
    ];

    $handler = Services_NzbHandler_Factory::build($settings, $action, $nzbHandling);
    if ($handler instanceof Services_NzbHandler_Disable) {
        tool_out(['error' => TOOL_SEND_HANDLERS[$action] . ' is not reachable right now.'], 502);
    }
    $handler->processNzb($fullSpot, [['spot' => $fullSpot, 'nzb' => $nzb]]);

    tool_out(['ok' => true, 'handler' => TOOL_SEND_HANDLERS[$action]]);
} catch (PermissionDeniedException $e) {
    tool_out(['error' => 'You are not allowed to send NZBs to a download client.'], 403);
} catch (Throwable $e) {
    error_log('nzbgeek-send: ' . $e->getMessage());
    tool_out(['error' => 'Sending failed: ' . $e->getMessage()], 502);
}
