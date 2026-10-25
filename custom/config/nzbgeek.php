<?php
/**
 * NZBGeek configuration for custom/tools/nzbgeek.php.
 *
 * Users normally enter their own key as "NZBGeek API key" under
 * Change preferences in Spotweb. A key set here is the shared fallback
 * for users who have not entered one.
 *
 * Get a key at https://nzbgeek.info/dashboard.php ("API Key").
 *
 * This file survives Spotweb updates because it lives in /custom/.
 */
return [
    'api_key' => '',
    'base_url' => 'https://api.nzbgeek.info',
];
