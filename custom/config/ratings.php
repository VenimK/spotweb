<?php
/**
 * Ratings configuration for custom/tools/ratings.php.
 *
 * IMDb ratings work out of the box (via Cinemeta, no key needed).
 * TMDb ratings need a free API key:
 *   1. Create an account at https://www.themoviedb.org/
 *   2. Request an API key at https://www.themoviedb.org/settings/api
 *   3. Paste the "API Key (v3 auth)" value below.
 *
 * This file survives Spotweb updates because it lives in /custom/.
 */
return [
    'tmdb_api_key' => '',
];
