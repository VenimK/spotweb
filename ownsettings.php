<?php

// Force Dutch language for all users
$settings['force_language'] = 'nl_NL';

// Prestatie optimalisatie instellingen
$settings['performance_cache'] = true; // Schakel caching in
$settings['performance_cache_ttl'] = 3600; // Cache TTL in seconden (1 uur)
$settings['performance_lazy_load'] = true; // Schakel lazy loading in
$settings['performance_monitoring'] = true; // Schakel prestatiemonitoring in
$settings['performance_resource_priority'] = true; // Schakel resource prioritization in
$settings['performance_prefetching'] = true; // Schakel content prefetching in
$settings['performance_browser_cache'] = true; // Schakel browser caching optimalisaties in

// Performance optimization settings
$settings['performance_monitoring_enabled'] = true;
$settings['cache_enabled'] = true;
$settings['cache_method'] = 'file';
$settings['cache_ttl'] = 3600;
