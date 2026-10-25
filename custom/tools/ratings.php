<?php
/**
 * IMDb / TMDb rating lookup for spot detail pages.
 *
 * Called by custom/js/ratings.js. Requires a valid Spotweb session
 * (the "spotsession" cookie) and returns JSON only.
 *
 * Optional TMDb support: enter a free key from
 * https://www.themoviedb.org/settings/api as "TMDB API key" in your
 * Spotweb preferences, or set 'tmdb_api_key' in custom/config/ratings.php
 * as a fallback for all users.
 */
require dirname(__DIR__) . '/includes/tool-session.inc.php';

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
 * becomes "Movie Title". The year is still read from the raw title
 * by extract_year() and used for matching, so it is also stripped.
 */
function clean_title(string $title): string
{
    $t = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/[._]+/', ' ', $t);
    $t = preg_replace('/[[\](){}\'"“”‘’<>]/', ' ', $t);
    /* Everything after the first year, SxxEyy or resolution is release noise */
    if (preg_match('/\b(19\d{2}|20\d{2})\b|\bS\d{1,2}E?\d*\b|\b(480|576|720|900|1080|1440|2160|4320)p\b/i', $t, $m, PREG_OFFSET_CAPTURE) && $m[0][1] > 0) {
        $t = substr($t, 0, $m[0][1]);
    }
    $t = preg_replace('/\b(Season|Complete|Part|Disc|CD)\s*\d*\b/i', ' ', $t);
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

/*
 * Poster, plot, runtime, genres, top cast and YouTube trailer for a TMDb
 * movie or TV show, cached for a week per TMDb id.
 */
function tmdb_details(PDO $pdo, string $kind, int $id, string $apiKey): ?array
{
    $cacheKey = 'tmdbd:' . $kind . ':' . $id;
    $cached = cache_get($pdo, $cacheKey, 7 * 24 * 3600);
    if ($cached !== null) {
        return $cached ?: null;
    }

    $data = http_get_json("https://api.themoviedb.org/3/{$kind}/{$id}?append_to_response=videos,credits&api_key="
        . rawurlencode($apiKey));
    if (!is_array($data) || empty($data['id'])) {
        return null;
    }

    /* Prefer an official YouTube trailer, then any trailer, then a teaser */
    $trailer = null;
    $bestRank = 99;
    foreach ($data['videos']['results'] ?? [] as $video) {
        if (($video['site'] ?? '') !== 'YouTube' || !preg_match('/^[A-Za-z0-9_-]{6,20}$/', (string) ($video['key'] ?? ''))) {
            continue;
        }
        $rank = (($video['type'] ?? '') === 'Trailer' ? 0 : (($video['type'] ?? '') === 'Teaser' ? 2 : 4))
            + (empty($video['official']) ? 1 : 0);
        if ($rank < $bestRank) {
            $bestRank = $rank;
            $trailer = (string) $video['key'];
        }
    }

    $runtime = (int) ($data['runtime'] ?? ($data['episode_run_time'][0] ?? 0));
    $date = (string) ($data['release_date'] ?? $data['first_air_date'] ?? '');
    $details = [
        'title' => (string) ($data['title'] ?? $data['name'] ?? ''),
        'year' => preg_match('/^\d{4}/', $date, $y) ? $y[0] : '',
        'tagline' => (string) ($data['tagline'] ?? ''),
        'overview' => (string) ($data['overview'] ?? ''),
        'runtime' => $runtime > 0 ? $runtime : null,
        'seasons' => isset($data['number_of_seasons']) ? (int) $data['number_of_seasons'] : null,
        'genres' => array_values(array_map(fn ($g) => (string) $g['name'], $data['genres'] ?? [])),
        'cast' => array_values(array_map(fn ($c) => (string) $c['name'], array_slice($data['credits']['cast'] ?? [], 0, 6))),
        'poster' => !empty($data['poster_path']) ? 'https://image.tmdb.org/t/p/w342' . $data['poster_path'] : null,
        'trailer' => $trailer,
    ];
    cache_put($pdo, $cacheKey, $details);
    return $details;
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

function cache_get(PDO $pdo, string $key, ?int $fixedTtl = null): ?array
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
        $ttl = $fixedTtl ?? (!empty($decoded['imdb_id']) ? 7 * 24 * 3600 : 6 * 3600);
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

[$pdo, $userId] = tool_boot();

/* TMDb key: the user's own key from Spotweb preferences, else custom/config/ratings.php */
$tmdbKey = tool_api_key(tool_user_prefs($pdo, $userId), 'tmdb_api_key', 'ratings.php', 'tmdb_api_key');

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

/* Results with and without TMDb data are cached separately */
$cacheKey = ($tmdbKey !== '' ? 't' : '') . ($imdbId !== ''
    ? 'imdb:' . $imdbId
    : 'title:' . md5(mb_strtolower($rawTitle) . '|' . ($year ?? '')));

$result = cache_get($pdo, $cacheKey);
if ($result === null) {
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

    /*
     * Last resort: the resolved title turned out to have no rating at all
     * (obscure or wrong match), so let a plain TMDb title search try.
     */
    if ($tmdbKey !== '' && $title !== ''
        && $response['imdb_rating'] === null && $response['tmdb_rating'] === null) {
        $hit = tmdb_search($title, $year, $tmdbKey);
        if ($hit !== null && $hit['rating'] !== null) {
            $response['tmdb_rating'] = $hit['rating'];
            $response['type'] = $response['type'] ?? ($hit['kind'] === 'tv' ? 'series' : 'movie');
            $response['name'] = $response['name'] ?? ($hit['name'] !== '' ? $hit['name'] : null);
            if ($hit['id'] > 0) {
                $response['tmdb_url'] = 'https://www.themoviedb.org/' . $hit['kind'] . '/' . $hit['id'];
            }
            if ($hit['imdb_id'] !== null && $hit['imdb_id'] !== $imdbId) {
                $imdbId = $hit['imdb_id'];
                $response['imdb_id'] = $imdbId;
                $meta = cinemeta_rating($imdbId, $response['type']);
                if ($meta !== null) {
                    $response['imdb_rating'] = $meta['rating'];
                    $response['type'] = $meta['type'];
                    if ($response['name'] === null && $meta['name'] !== '') {
                        $response['name'] = $meta['name'];
                    }
                }
            }
        }
    }

    cache_put($pdo, $cacheKey, $response);
    $result = $response;
}

/* Spot detail page: poster, plot, cast, runtime, genres and trailer from TMDb */
if (!empty($_GET['details']) && $tmdbKey !== '' && preg_match('~/(movie|tv)/(\d+)$~', (string) $result['tmdb_url'], $m)) {
    $result['details'] = tmdb_details($pdo, $m[1], (int) $m[2], $tmdbKey);
}
tool_out($result);
