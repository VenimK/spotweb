<?php
/**
 * IMDb / TMDb rating lookup for spot detail pages.
 *
 * Called by custom/js/ratings.js. Requires a valid Spotweb session
 * (the "spotsession" cookie) and returns JSON only.
 *
 * Optional TMDb support: create/edit custom/config/ratings.php and set
 * 'tmdb_api_key' to a free key from https://www.themoviedb.org/settings/api
 */
$root = dirname(__DIR__, 2);
$dbFile = $root . '/dbsettings.inc.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!is_readable($dbFile)) {
    http_response_code(500);
    exit(json_encode(['error' => 'Spotweb database settings are unavailable.']));
}
require $dbFile;

function out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function connect_db(array $settings): PDO
{
    $engine = (string) ($settings['engine'] ?? 'mysql');
    if (in_array($engine, ['mysql', 'pdo_mysql'], true)) {
        $driver = 'mysql';
    } elseif (in_array($engine, ['pgsql', 'pdo_pgsql', 'postgres'], true)) {
        $driver = 'pgsql';
    } elseif (in_array($engine, ['sqlite', 'pdo_sqlite'], true)) {
        $pdo = new PDO('sqlite:' . ($settings['path'] ?? $settings['dbname'] ?? ''));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } else {
        $driver = $engine;
    }
    $host = $settings['host'] ?? '127.0.0.1';
    $port = $settings['port'] ?? ($driver === 'pgsql' ? '5432' : '3306');
    $dsn = sprintf('%s:host=%s;port=%s;dbname=%s', $driver, $host, $port, $settings['dbname'] ?? '');
    if ($driver === 'mysql') {
        $dsn .= ';charset=utf8mb4';
    }
    $pdo = new PDO($dsn, $settings['user'] ?? '', $settings['pass'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function http_get_json(string $url): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'SpotwebRatings/1.0 (+https://github.com/VenimK/spotweb)',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($body === false || $code !== 200) {
        return null;
    }
    $decoded = json_decode((string) $body, true);
    return is_array($decoded) ? $decoded : null;
}

/*
 * Removes release-name noise (quality tags, scene group suffix, etc.)
 * so a spot title like "Movie.Title.2024.1080p.WEB-DL.x264-GROUP"
 * becomes "Movie Title 2024".
 */
function clean_title(string $title): string
{
    $t = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/[._]+/', ' ', $t);
    $t = preg_replace('/[[\](){}\'"“”‘’<>]/', ' ', $t);
    $t = preg_replace('/\bS\d{1,2}(E\d{1,3})?\b/i', ' ', $t);
    $t = preg_replace('/\b(Season|Complete|Part|Disc|CD)\s*\d*\b/i', ' ', $t);
    $noise = '480p|576p|720p|900p|1080p|1440p|2160p|4320p|4k|8k|uhd|fhd|hd|sd|'
        . 'hdr10\+?|hdr|dv|dolby|vision|blu-?ray|bd-?remux|remux|bdrip|brrip|bdr|'
        . 'web-?dl|webrip|web|hdtv|hdrip|dvdrip|dvdscr|dvd|cam|ts|tc|scr|screener|r5|line|'
        . 'x264|x265|h\.?264|h\.?265|hevc|avc|xvid|divx|mpeg2?|av1|vp9|'
        . 'aac\d?|eac3|e-ac-3|ac3|dts(-?x|x|-?hd|-?es|-?ma)?|truehd|atmos|ddp?\d?(\.\d)?|mp3|flac|pcm|opus|'
        . 'proper|repack|extended|unrated|remaster(ed)?|internal|limited|imax|openmatte|'
        . 'multi(sub|language|s|audio)?|dual|dubbed|subbed|nlsubs?|nlsd|retail|custom|'
        . 'nf|amzn|amazon|hmax|max|hulu|dsnp|disney|atvp|apple|pcok|crav|stan|binge|shd|'
        . 'ita|eng|ger|fre|fra|dut|nl|nlz|esp|por|rus|subs?|hc';
    $t = preg_replace('/\b(' . $noise . ')\b/i', ' ', $t);
    $t = preg_replace('/-\s*[A-Za-z0-9]+$/', ' ', $t); // trailing scene group
    $t = preg_replace('/\b(\d{3,4}mb|\d+(\.\d+)?gb)\b/i', ' ', $t);
    return trim((string) preg_replace('/\s+/', ' ', $t));
}

function extract_year(string $title): ?int
{
    if (preg_match('/\b(19\d{2}|20\d{2})\b/', $title, $m)) {
        return (int) $m[1];
    }
    return null;
}

/*
 * Title -> IMDb id via IMDb's public suggestion endpoint
 * (same endpoint imdb.com uses for its search box).
 */
function imdb_suggest(string $title, ?int $year): ?array
{
    $first = strtolower(substr($title, 0, 1));
    if (!preg_match('/[a-z0-9]/', $first)) {
        return null;
    }
    $url = 'https://v2.sg.media-imdb.com/suggestion/'
        . rawurlencode($first) . '/'
        . rawurlencode(strtolower(str_replace(' ', '_', $title))) . '.json';
    $data = http_get_json($url);
    if (empty($data['d']) || !is_array($data['d'])) {
        return null;
    }
    $fallback = null;
    foreach ($data['d'] as $entry) {
        $id = (string) ($entry['id'] ?? '');
        if (!preg_match('/^tt\d{6,9}$/', $id)) {
            continue;
        }
        $qid = (string) ($entry['qid'] ?? $entry['q'] ?? '');
        if ($qid !== '' && !in_array($qid, ['movie', 'feature', 'tvSeries', 'tvMiniSeries', 'tvMovie', 'tvSpecial', 'short', 'video'], true)) {
            continue;
        }
        $entryYear = isset($entry['y']) ? (int) $entry['y'] : null;
        if ($fallback === null) {
            $fallback = ['id' => $id, 'qid' => $qid, 'year' => $entryYear];
        }
        if ($year !== null && $entryYear !== null && abs($entryYear - $year) <= 1) {
            return ['id' => $id, 'qid' => $qid, 'year' => $entryYear];
        }
    }
    return $fallback;
}

/*
 * IMDb rating via Cinemeta (no key required).
 * Returns ['type' => 'movie'|'series', 'rating' => float, 'name' => string]
 */
function cinemeta_rating(string $imdbId, ?string $type): ?array
{
    $order = ($type === 'series') ? ['series', 'movie'] : ['movie', 'series'];
    foreach ($order as $t) {
        $data = http_get_json("https://v3-cinemeta.strem.io/meta/{$t}/{$imdbId}.json");
        $meta = $data['meta'] ?? null;
        if (!is_array($meta) || empty($meta['id'])) {
            continue;
        }
        $rating = null;
        if (isset($meta['imdbRating']) && is_numeric($meta['imdbRating'])) {
            $rating = (float) $meta['imdbRating'];
        }
        return [
            'type' => (string) ($meta['type'] ?? $t),
            'rating' => $rating,
            'name' => (string) ($meta['name'] ?? ''),
            'year' => (string) ($meta['releaseInfo'] ?? ''),
        ];
    }
    return null;
}

/*
 * TMDb rating via the free API (requires custom/config/ratings.php
 * 'tmdb_api_key'). Resolves the IMDb id to a TMDb entry so we can
 * link to it directly.
 */
function tmdb_rating(string $imdbId, string $apiKey): ?array
{
    $data = http_get_json('https://api.themoviedb.org/3/find/' . rawurlencode($imdbId)
        . '?external_source=imdb_id&api_key=' . rawurlencode($apiKey));
    if (!is_array($data)) {
        return null;
    }
    foreach (['movie_results' => 'movie', 'tv_results' => 'tv'] as $list => $kind) {
        $hit = $data[$list][0] ?? null;
        if (is_array($hit) && isset($hit['vote_average']) && (float) $hit['vote_average'] > 0) {
            return [
                'rating' => (float) $hit['vote_average'],
                'id' => (int) ($hit['id'] ?? 0),
                'kind' => $kind,
            ];
        }
    }
    return null;
}

/*
 * Fallback when no IMDb id can be resolved: search TMDb by title.
 * Returns the hit plus its IMDb id (via /external_ids) so the IMDb
 * rating can still be fetched afterwards.
 */
function tmdb_search(string $title, ?int $year, string $apiKey): ?array
{
    $apiKey = rawurlencode($apiKey);
    foreach (['movie' => 'year', 'tv' => 'first_air_date_year'] as $kind => $yearParam) {
        $url = "https://api.themoviedb.org/3/search/{$kind}?api_key={$apiKey}&query="
            . rawurlencode($title) . ($year !== null ? "&{$yearParam}={$year}" : '');
        $data = http_get_json($url);
        $hit = $data['results'][0] ?? null;
        if (!is_array($hit)) {
            continue;
        }
        $ext = http_get_json("https://api.themoviedb.org/3/{$kind}/" . (int) $hit['id']
            . "/external_ids?api_key={$apiKey}");
        return [
            'id' => (int) $hit['id'],
            'kind' => $kind,
            'imdb_id' => preg_match('/^tt\d{6,9}$/', (string) ($ext['imdb_id'] ?? '')) ? $ext['imdb_id'] : null,
            'rating' => isset($hit['vote_average']) && (float) $hit['vote_average'] > 0 ? (float) $hit['vote_average'] : null,
            'name' => (string) ($hit['title'] ?? $hit['name'] ?? ''),
        ];
    }
    return null;
}

/* Cache table (created lazily; lookups still work if creation fails) */
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
        /* Resolved titles stay fresh for a week, misses for 6 hours */
        $ttl = !empty($decoded['imdb_id']) ? 7 * 24 * 3600 : 6 * 3600;
        if ((time() - (int) $row['fetched']) < $ttl) {
            return $decoded;
        }
    } catch (Throwable $e) {
        // cache unavailable - fall through to a live lookup
    }
    return null;
}

function cache_put(PDO $pdo, string $key, array $payload): void
{
    try {
        cache_table($pdo);
        $stmt = $pdo->prepare('DELETE FROM rating_cache WHERE cachekey = ?');
        $stmt->execute([$key]);
        $stmt = $pdo->prepare('INSERT INTO rating_cache (cachekey, payload, fetched) VALUES (?, ?, ?)');
        $stmt->execute([$key, json_encode($payload), time()]);
    } catch (Throwable $e) {
        // non-fatal
    }
}

/* ------------------------------------------------------------------ */

try {
    $pdo = connect_db($GLOBALS['dbsettings']);
} catch (Throwable $e) {
    out(['error' => 'Unable to connect to the Spotweb database.'], 500);
}

/*
 * Require a valid Spotweb session (same "spotsession" cookie Spotweb
 * itself checks). Anonymous sessions are never stored in the sessions
 * table, so for installs that allow anonymous access we also accept the
 * configured nonauthenticated_userid - such an install exposes the same
 * data publicly anyway.
 */
$sessionCookie = (string) ($_COOKIE['spotsession'] ?? '');
$sessionParts = explode('.', $sessionCookie);
$authed = false;
if (count($sessionParts) === 2 && $sessionParts[0] !== '' && ctype_digit($sessionParts[1])) {
    $cookieUserId = (int) $sessionParts[1];
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM sessions s INNER JOIN users u ON u.id = s.userid
            WHERE s.sessionid = ? AND s.userid = ? AND u.deleted = '0' LIMIT 1");
        $stmt->execute([$sessionParts[0], $cookieUserId]);
        $authed = (bool) $stmt->fetchColumn();
        if (!$authed) {
            $stmt = $pdo->prepare("SELECT value, serialized FROM settings WHERE name = 'nonauthenticated_userid' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            $anonId = 0;
            if ($row) {
                $anonId = (int) $row['value'];
                if (!empty($row['serialized'])) {
                    $decoded = @unserialize((string) $row['value'], ['allowed_classes' => false]);
                    if (is_numeric($decoded)) {
                        $anonId = (int) $decoded;
                    }
                }
            }
            if ($anonId > 0 && $cookieUserId === $anonId) {
                $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND deleted = '0' LIMIT 1");
                $stmt->execute([$anonId]);
                $authed = (bool) $stmt->fetchColumn();
            }
        }
    } catch (Throwable $e) {
        $authed = false;
    }
}
if (!$authed) {
    out(['error' => 'A Spotweb login is required.'], 401);
}

/* Optional TMDb API key */
$cfgFile = dirname(__DIR__) . '/config/ratings.php';
$config = is_file($cfgFile) ? include $cfgFile : [];
$tmdbKey = is_array($config) ? trim((string) ($config['tmdb_api_key'] ?? '')) : '';

/* Input: an IMDb id, or a (raw) spot title we resolve ourselves */
$imdbId = '';
$rawTitle = trim((string) ($_GET['title'] ?? ''));
$type = null;

if (preg_match('/^tt\d{6,9}$/', (string) ($_GET['imdb'] ?? ''))) {
    $imdbId = (string) $_GET['imdb'];
}

$year = extract_year($rawTitle);
if (isset($_GET['year']) && preg_match('/^(19|20)\d{2}$/', (string) $_GET['year'])) {
    $year = (int) $_GET['year'];
}

$cacheKey = $imdbId !== ''
    ? 'imdb:' . $imdbId
    : 'title:' . md5(mb_strtolower($rawTitle) . '|' . ($year ?? ''));

$result = cache_get($pdo, $cacheKey);
if ($result !== null) {
    out($result + ['cached' => true]);
}

$title = clean_title($rawTitle);

/* Resolve the IMDb id from the title when none was supplied */
if ($imdbId === '' && $title !== '') {
    $hit = imdb_suggest($title, $year);
    if ($hit !== null) {
        $imdbId = $hit['id'];
        $type = in_array($hit['qid'], ['tvSeries', 'tvMiniSeries'], true) ? 'series' : 'movie';
    }
}

$response = [
    'imdb_id' => $imdbId !== '' ? $imdbId : null,
    'imdb_rating' => null,
    'tmdb_rating' => null,
    'type' => null,
    'name' => null,
    'tmdb_url' => null,
];

/* TMDb fallback: find the title even when no IMDb id could be resolved */
if ($imdbId === '' && $title !== '' && $tmdbKey !== '') {
    $hit = tmdb_search($title, $year, $tmdbKey);
    if ($hit !== null) {
        $response['tmdb_rating'] = $hit['rating'];
        $response['type'] = $hit['kind'] === 'tv' ? 'series' : 'movie';
        $response['name'] = $hit['name'] !== '' ? $hit['name'] : null;
        if ($hit['id'] > 0) {
            $response['tmdb_url'] = 'https://www.themoviedb.org/' . $hit['kind'] . '/' . $hit['id'];
        }
        /* The search result also gives us the IMDb id for the IMDb rating */
        if ($hit['imdb_id'] !== null) {
            $imdbId = $hit['imdb_id'];
            $response['imdb_id'] = $imdbId;
        }
    }
}

if ($imdbId !== '') {
    $meta = cinemeta_rating($imdbId, $type);
    if ($meta !== null) {
        $response['imdb_rating'] = $meta['rating'];
        if ($response['type'] === null) {
            $response['type'] = $meta['type'];
        }
        if ($response['name'] === null && $meta['name'] !== '') {
            $response['name'] = $meta['name'];
        }
    }
    if ($tmdbKey !== '' && $response['tmdb_rating'] === null) {
        $tmdb = tmdb_rating($imdbId, $tmdbKey);
        if ($tmdb !== null) {
            $response['tmdb_rating'] = $tmdb['rating'];
            if ($tmdb['id'] > 0) {
                $response['tmdb_url'] = 'https://www.themoviedb.org/' . $tmdb['kind'] . '/' . $tmdb['id'];
            }
        }
    }
}

cache_put($pdo, $cacheKey, $response);
out($response);
