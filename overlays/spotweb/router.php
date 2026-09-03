<?php
/**
 * Router for PHP's built-in server (php -S).
 *
 * Start Spotweb with:
 *   /opt/homebrew/opt/php@8.2/bin/php -S 127.0.0.1:9999 -t . router.php
 *
 * Provides:
 * - /api → index.php?page=newznabapi (same as .htaccess)
 * - Cache-Control / Expires for static assets
 * - Optional gzip for text assets when the client accepts it
 */

declare(strict_types=1);

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uriPath = is_string($uriPath) ? rawurldecode($uriPath) : '/';

// Normalize and block path traversal
$uriPath = str_replace('\\', '/', $uriPath);
if ($uriPath === '' || $uriPath[0] !== '/') {
    $uriPath = '/'.$uriPath;
}
if (strpos($uriPath, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', $uriPath)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Bad request\n";

    return true;
}

// Newznab API rewrite (mirrors .htaccess)
if ($uriPath === '/api' || $uriPath === '/api/') {
    $_GET['page'] = $_GET['page'] ?? 'newznabapi';
    $_REQUEST['page'] = $_REQUEST['page'] ?? 'newznabapi';
    require __DIR__.'/index.php';

    return true;
}

$relative = ltrim($uriPath, '/');
$file = $relative === '' ? '' : realpath(__DIR__.'/'.$relative);
$root = realpath(__DIR__);

// Only touch real files under the Spotweb root
if ($file === false || $root === false || !is_file($file) || strpos($file, $root.DIRECTORY_SEPARATOR) !== 0) {
    return false;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

// Let the built-in server execute PHP as usual
if ($ext === 'php' || $ext === 'inc' || $ext === '') {
    return false;
}

$types = [
    'js'    => ['application/javascript; charset=utf-8', 900],
    'mjs'   => ['application/javascript; charset=utf-8', 900],
    'css'   => ['text/css; charset=utf-8', 900],
    'map'   => ['application/json; charset=utf-8', 900],
    'json'  => ['application/json; charset=utf-8', 300],
    'xml'   => ['application/xml; charset=utf-8', 90],
    'txt'   => ['text/plain; charset=utf-8', 62],
    'html'  => ['text/html; charset=utf-8', 90],
    'htm'   => ['text/html; charset=utf-8', 90],
    'gif'   => ['image/gif', 14400],
    'jpg'   => ['image/jpeg', 14400],
    'jpeg'  => ['image/jpeg', 14400],
    'png'   => ['image/png', 14400],
    'bmp'   => ['image/bmp', 14400],
    'webp'  => ['image/webp', 14400],
    'svg'   => ['image/svg+xml', 14400],
    'ico'   => ['image/x-icon', 14400],
    'woff'  => ['font/woff', 604800],
    'woff2' => ['font/woff2', 604800],
    'ttf'   => ['font/ttf', 604800],
    'otf'   => ['font/otf', 604800],
    'eot'   => ['application/vnd.ms-fontobject', 604800],
];

if (!isset($types[$ext])) {
    return false;
}

[$contentType, $maxAge] = $types[$ext];
$mtime = filemtime($file) ?: time();
$size = filesize($file);
$etag = '"'.md5($file.'|'.$mtime.'|'.$size).'"';

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    header('ETag: '.$etag);
    header('Cache-Control: public, max-age='.$maxAge);
    header('Expires: '.gmdate('D, d M Y H:i:s', time() + $maxAge).' GMT');

    return true;
}

if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
    $since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    if ($since !== false && $since >= $mtime) {
        http_response_code(304);
        header('ETag: '.$etag);
        header('Cache-Control: public, max-age='.$maxAge);
        header('Expires: '.gmdate('D, d M Y H:i:s', time() + $maxAge).' GMT');

        return true;
    }
}

header('Content-Type: '.$contentType);
header('Cache-Control: public, max-age='.$maxAge);
header('Expires: '.gmdate('D, d M Y H:i:s', time() + $maxAge).' GMT');
header('ETag: '.$etag);
header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');

$compressible = in_array($ext, ['js', 'mjs', 'css', 'map', 'json', 'xml', 'txt', 'html', 'htm', 'svg'], true);
$acceptsGzip = $compressible
    && isset($_SERVER['HTTP_ACCEPT_ENCODING'])
    && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false
    && $size > 1024
    && $size < 2 * 1024 * 1024;

if ($acceptsGzip) {
    $raw = file_get_contents($file);
    if ($raw === false) {
        http_response_code(500);

        return true;
    }
    $encoded = gzencode($raw, 6);
    if ($encoded !== false) {
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding');
        header('Content-Length: '.strlen($encoded));
        echo $encoded;

        return true;
    }
}

header('Content-Length: '.$size);
readfile($file);

return true;
