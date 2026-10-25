<?php
/**
 * NZBGeek search proxy for Spotweb.
 *
 * Called by custom/js/nzbgeek.js when the index page contains a search
 * query. Requires a valid Spotweb session (the "spotsession" cookie,
 * same check as custom/tools/ratings.php) and returns JSON only.
 *
 * Enter your NZBGeek API key (Dashboard -> API Key on nzbgeek.info) as
 * "NZBGeek API key" in your Spotweb preferences, or set 'api_key' in
 * custom/config/nzbgeek.php as a fallback for all users.
 */
require dirname(__DIR__) . '/includes/tool-session.inc.php';

function http_get(string $url): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'SpotwebNzbGeek/1.0 (+https://github.com/VenimK/spotweb)',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($body === false || $code !== 200) {
        return null;
    }
    return (string) $body;
}

/* Cache table is shared with ratings.php */
function cache_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo->exec('CREATE TABLE IF NOT EXISTS rating_cache (
        cachekey VARCHAR(80) PRIMARY KEY,
        payload TEXT,
        fetched BIGINT NOT NULL
    )');
}

function cache_get(PDO $pdo, string $key): ?array
{
    try {
        cache_table($pdo);
        $stmt = $pdo->prepare('SELECT payload, fetched FROM rating_cache WHERE cachekey = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $decoded = json_decode((string) $row['payload'], true);
        if (!is_array($decoded)) {
            return null;
        }
        $ttl = (int) ($decoded['_ttl'] ?? 120);
        if ((time() - (int) $row['fetched']) < $ttl) {
            unset($decoded['_ttl']);
            return $decoded;
        }
    } catch (Throwable $e) {
        // cache unavailable - fall through to a live lookup
    }
    return null;
}

function cache_put(PDO $pdo, string $key, array $payload, int $ttl): void
{
    try {
        cache_table($pdo);
        $payload['_ttl'] = $ttl;
        $stmt = $pdo->prepare('DELETE FROM rating_cache WHERE cachekey = ?');
        $stmt->execute([$key]);
        $stmt = $pdo->prepare('INSERT INTO rating_cache (cachekey, payload, fetched) VALUES (?, ?, ?)');
        $stmt->execute([$key, json_encode($payload), time()]);
    } catch (Throwable $e) {
        // non-fatal
    }
}

/* ------------------------------------------------------------------ */

[$pdo, $userId] = tool_boot();

/* NZBGeek key: the user's own key from Spotweb preferences, else custom/config/nzbgeek.php */
$prefs = tool_user_prefs($pdo, $userId);
$apiKey = tool_api_key($prefs, 'nzbgeek_api_key', 'nzbgeek.php', 'api_key');
$handlerAction = (string) ($prefs['nzbhandling']['action'] ?? 'disable');
/* Label for the per-row "send to download client" button, null when unsupported */
$handler = TOOL_SEND_HANDLERS[$handlerAction] ?? null;
$cfgFile = dirname(__DIR__) . '/config/nzbgeek.php';
$config = is_file($cfgFile) ? include $cfgFile : [];
$baseUrl = rtrim((string) (is_array($config) ? ($config['base_url'] ?? '') : ''), '/') ?: 'https://api.nzbgeek.info';
if ($apiKey === '') {
    tool_out([
        'error' => 'No NZBGeek API key: enter yours under Change preferences.',
        'prefs_url' => '?page=edituserprefs&userid=' . $userId,
    ], 400);
}

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2 || mb_strlen($q) > 200) {
    tool_out(['error' => 'Missing or invalid query.'], 400);
}
$cat = preg_replace('/[^0-9,]/', '', (string) ($_GET['cat'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 25);
$limit = max(1, min(100, $limit));

/* NZB links embed the API key, so cached results must never be shared across keys */
$cacheKey = 'nzbgeek:' . md5($apiKey . '|' . mb_strtolower($q) . '|' . $cat . '|' . $limit);
$cached = cache_get($pdo, $cacheKey);
if ($cached !== null) {
    tool_out($cached + ['handler' => $handler, 'cached' => true]);
}

$url = $baseUrl . '/api?t=search&apikey=' . rawurlencode($apiKey)
    . '&q=' . rawurlencode($q) . '&limit=' . $limit
    . ($cat !== '' ? '&cat=' . $cat : '')
    . '&extended=1';
$body = http_get($url);
if ($body === null) {
    tool_out(['error' => 'NZBGeek is unreachable.'], 502);
}
if (strpos($body, 'code="100"') !== false || stripos($body, 'incorrect user credentials') !== false) {
    tool_out(['error' => 'NZBGeek rejected the API key.'], 502);
}

$xml = @simplexml_load_string($body);
if ($xml === false) {
    tool_out(['error' => 'Unexpected response from NZBGeek.'], 502);
}

$total = 0;
$responseNode = $xml->xpath('//newznab:response');
if (!empty($responseNode)) {
    $total = (int) $responseNode[0]['total'];
}

$items = [];
foreach ($xml->xpath('//channel/item') ?: [] as $item) {
    $attrs = [];
    foreach ($item->xpath('newznab:attr') ?: [] as $attr) {
        $attrs[(string) $attr['name']] = (string) $attr['value'];
    }
    $size = (int) ($attrs['size'] ?? 0);
    if ($size === 0 && isset($item->enclosure)) {
        $size = (int) $item->enclosure['length'];
    }
    $link = (string) $item->link;
    if ($link === '') {
        $link = (string) $item->guid;
    }
    $items[] = [
        'title' => (string) $item->title,
        'nzb' => $link,
        'guid' => (string) $item->guid,
        'id' => preg_match('/^[a-f0-9]{16,64}$/i', $attrs['guid'] ?? '') ? $attrs['guid'] : '',
        'catid' => (int) ($attrs['category'] ?? 0),
        'date' => strtotime((string) $item->pubDate) ?: null,
        'size' => $size,
        'category' => (string) $item->category ?: ($attrs['category'] ?? ''),
        'poster' => $attrs['poster'] ?? '',
        'grabs' => (int) ($attrs['grabs'] ?? 0),
    ];
}

$response = ['total' => $total, 'items' => $items];
cache_put($pdo, $cacheKey, $response, 120);
tool_out($response + ['handler' => $handler]);
