<?php
/**
 * Spotweb Custom Theme Loader
 * 
 * This file is the ONLY integration point between core Spotweb and custom themes.
 * It loads custom theme CSS files and injects the theme switcher JavaScript.
 * 
 * Integration: Add this line to templates/we1rdo/includes/header.inc.php:
 * <?php include_once(__DIR__ . '/../../../custom/includes/theme-loader.inc.php'); ?>
 */

if (ob_get_level() === 0) {
    ob_start();
}

// Base paths
$customBase = realpath(__DIR__ . '/..');
$preinstalledThemesPath = $customBase . '/themes/preinstalled';
$customThemesPath = $customBase . '/themes';
$jsPath = $customBase . '/js';

// Load pre-installed theme CSS files
$preinstalledThemes = [
    'dark',
    'midnight-ocean',
    'cyberpunk',
    'nord',
    'dracula',
    'forest',
    'sunset',
    'spring',
    'summer',
    'autumn',
    'winter'
];

foreach ($preinstalledThemes as $theme) {
    $themeFile = $preinstalledThemesPath . '/theme-' . $theme . '.css';
    if (file_exists($themeFile)) {
        echo "<link rel='stylesheet' type='text/css' href='custom/themes/preinstalled/theme-{$theme}.css'>\n";
    }
}

// Load custom user themes (from custom/themes/ root, not preinstalled/)
$customThemeFiles = glob($customThemesPath . '/theme-*.css');
if ($customThemeFiles) {
    foreach ($customThemeFiles as $themeFile) {
        $themeName = basename($themeFile);
        echo "<link rel='stylesheet' type='text/css' href='custom/themes/{$themeName}'>\n";
    }
}

// Load theme switcher JavaScript
$themeSwitcherJs = $jsPath . '/theme-switcher.js';
if (file_exists($themeSwitcherJs)) {
    echo "<script src='custom/js/theme-switcher.js'></script>\n";
}

// Add theme switcher styles (inline to avoid extra file)
?>
<style>
/* Theme Switcher Styles */
.theme-dropdown {
    position: relative;
    display: inline-block;
}

.theme-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: inherit;
    text-decoration: none;
}

.theme-toggle:hover {
    background: rgba(0, 0, 0, 0.3);
    border-color: rgba(255, 255, 255, 0.2);
}

.theme-icon {
    font-size: 18px;
}

.theme-name {
    font-size: 14px;
    font-weight: 500;
}

.theme-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    background: #2d2d2d;
    border: 1px solid #444;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    min-width: 200px;
    z-index: 9999;
    overflow: hidden;
}

.theme-menu.show {
    display: block;
    animation: slideDown 0.2s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.theme-menu ul {
    list-style: none;
    margin: 0;
    padding: 8px 0;
}

.theme-menu li {
    padding: 0;
}

.theme-menu a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: #e0e0e0;
    text-decoration: none;
    transition: background 0.2s ease;
}

.theme-menu a:hover {
    background: rgba(255, 255, 255, 0.1);
}

.theme-menu a.active {
    background: rgba(100, 150, 255, 0.2);
    border-left: 3px solid #6496ff;
}

.theme-menu .theme-icon {
    font-size: 16px;
}

.theme-menu .theme-name {
    flex: 1;
    font-size: 14px;
}

/* Modern template mapping (works with templates/modern that relies on CSS variables) */
:root[data-sw-theme],
:root[data-sw-theme] body {
    background: var(--color-bg, var(--color-surface, #111)) !important;
    color: var(--color-text, #e5e7eb) !important;
}

:root[data-sw-theme] a {
    color: var(--color-accent, #60a5fa);
}

:root[data-sw-theme] div#toolbar {
    background: var(--sw-strip-gradient, linear-gradient(180deg, #181c22, #12161c)) !important;
    border-bottom: 1px solid var(--sw-strip-border, rgba(0, 0, 0, 0.6)) !important;
}

:root[data-sw-theme] .toolbarButton p,
:root[data-sw-theme] .toolbarButton p a,
:root[data-sw-theme] .toolbarButton p span {
    color: var(--sw-strip-text, #f4f5f7) !important;
}

:root[data-sw-theme] .toolbarButton.dropdown ul ul {
    background: var(--color-surface, rgba(33, 38, 45, 0.96)) !important;
    border-color: var(--color-border, rgba(63, 70, 80, 0.9)) !important;
}

:root[data-sw-theme] .toolbarButton.dropdown ul ul li a {
    color: var(--color-text, #e3e7ef) !important;
}

:root[data-sw-theme] .toolbarButton.dropdown ul ul li a:hover {
    background: rgba(59, 130, 246, 0.18) !important;
    background: color-mix(in srgb, var(--color-accent, #3b82f6) 18%, transparent) !important;
}

:root[data-sw-theme] div#filter {
    background: var(--color-surface, #111) !important;
}

:root[data-sw-theme] div.filter {
    --filter-card-bg: var(--color-surface, rgba(17, 24, 39, 0.9));
    --filter-card-border: var(--color-border, rgba(148, 163, 184, 0.18));
    --filter-card-text: var(--color-text, rgba(226, 232, 240, 0.94));
    --filter-chip-bg: var(--color-accent, rgba(96, 165, 250, 0.85));
    --filter-muted: var(--color-muted, rgba(148, 163, 184, 0.7));
    --filter-backdrop: var(--color-surface, rgba(6, 9, 15, 0.78));
}

:root[data-sw-theme] .cardsGrid,
:root[data-sw-theme] .spotCard,
:root[data-sw-theme] .details,
:root[data-sw-theme] .sidebarPanel,
:root[data-sw-theme] .filterlist {
    color: var(--color-text, #e5e7eb) !important;
}

:root[data-sw-theme] .spotCard {
    background: var(--color-surface, #111) !important;
    border: 1px solid var(--color-border, rgba(255, 255, 255, 0.16)) !important;
}

:root[data-sw-theme] .spotCard .meta,
:root[data-sw-theme] .spotCard .meta a {
    color: var(--color-muted, rgba(229, 231, 235, 0.75)) !important;
}

:root[data-sw-theme] .spotCard .title a {
    color: var(--color-text, #e5e7eb) !important;
}

:root[data-sw-theme] .spotCard .actions a.nzb,
:root[data-sw-theme] .greyButton,
:root[data-sw-theme] .smallGreyButton {
    background: var(--color-accent, #3b82f6) !important;
    color: #fff !important;
    border: 0 !important;
}

:root[data-sw-theme] .spotCard .comments {
    color: var(--color-accent, #3b82f6) !important;
}

:root[data-sw-theme] .spotCard.spotcat0 .badge { background: var(--sw-cat0, var(--color-accent, #3b82f6)) !important; }
:root[data-sw-theme] .spotCard.spotcat1 .badge { background: var(--sw-cat1, #f59e0b) !important; }
:root[data-sw-theme] .spotCard.spotcat2 .badge { background: var(--sw-cat2, #22c55e) !important; }
:root[data-sw-theme] .spotCard.spotcat3 .badge { background: var(--sw-cat3, #ef4444) !important; }

:root[data-sw-theme] .cardsNotice {
    background: rgba(59, 130, 246, 0.12) !important;
    border-color: rgba(59, 130, 246, 0.45) !important;
    background: color-mix(in srgb, var(--color-accent, #3b82f6) 12%, transparent) !important;
    border-color: color-mix(in srgb, var(--color-accent, #3b82f6) 45%, transparent) !important;
    color: var(--color-text, #a57900) !important;
}

:root[data-sw-theme] .cardsPager a:focus,
:root[data-sw-theme] .tablePager a:focus {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.28) !important;
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-accent, #3b82f6) 28%, transparent) !important;
}

:root[data-sw-theme] table.spots tr.head th {
    background: var(--sw-strip-gradient, linear-gradient(180deg, #262b34 0%, #191d22 100%)) !important;
    color: var(--sw-strip-text, #f4f5f7) !important;
    border-bottom: 1px solid var(--sw-strip-border, rgba(0, 0, 0, 0.6)) !important;
}

:root[data-sw-theme] table.spots tr td {
    border-color: var(--color-border, rgba(255, 255, 255, 0.16)) !important;
    color: var(--color-text, #e5e7eb) !important;
}

:root[data-sw-theme] table.spots tr td a {
    color: var(--color-text, #e5e7eb) !important;
}

:root[data-sw-theme] table.spots tr:hover td {
    background: var(--color-surface-2, rgba(255, 255, 255, 0.06)) !important;
}

:root[data-sw-theme] table.spots tr.spotcat0 td { background: rgba(59, 130, 246, 0.18) !important; background: color-mix(in srgb, var(--sw-cat0, var(--color-accent, #3b82f6)) 18%, transparent) !important; }
:root[data-sw-theme] table.spots tr.spotcat1 td { background: rgba(245, 158, 11, 0.18) !important; background: color-mix(in srgb, var(--sw-cat1, #f59e0b) 18%, transparent) !important; }
:root[data-sw-theme] table.spots tr.spotcat2 td { background: rgba(34, 197, 94, 0.18) !important; background: color-mix(in srgb, var(--sw-cat2, #22c55e) 18%, transparent) !important; }
:root[data-sw-theme] table.spots tr.spotcat3 td { background: rgba(239, 68, 68, 0.18) !important; background: color-mix(in srgb, var(--sw-cat3, #ef4444) 18%, transparent) !important; }

:root[data-sw-theme] table.spots td.watch a::before {
    color: var(--sw-cat1, var(--color-accent, #fbbf24)) !important;
}

:root[data-sw-theme] table.spots td.watch a.add::before {
    color: var(--sw-cat3, var(--color-accent, #f87171)) !important;
}

:root[data-sw-theme] div.details {
    background: var(--color-surface, #111) !important;
    border-color: var(--color-border, rgba(255, 255, 255, 0.16)) !important;
}

:root[data-sw-theme] div.details table th,
:root[data-sw-theme] div.details table td {
    border-color: var(--color-border, rgba(255, 255, 255, 0.16)) !important;
}

:root[data-sw-theme] div.details table.spotheader th.nzb a.nzb,
:root[data-sw-theme] div.details table.spotheader th.nzb a.nzb.downloaded {
    background: var(--color-accent, var(--color-surface-2, #111)) !important;
    border-color: var(--color-accent, var(--color-border, rgba(255,255,255,0.16))) !important;
}

:root[data-sw-theme] div.details table.spotheader th.nzb a.nzb::before {
    color: #fff !important;
}

:root[data-sw-theme] div.sidebarPanel.advancedSearch,
:root[data-sw-theme] div.sidebarPanel.advancedSearch::before {
    background: var(--color-surface, #ffffff) !important;
    border-color: var(--color-border, rgba(209, 213, 219, 0.9)) !important;
    color: var(--color-text, #111827) !important;
}

:root[data-sw-theme] div.sidebarPanel.advancedSearch h4,
:root[data-sw-theme] div.sidebarPanel.advancedSearch h4 a {
    background: var(--color-surface-2, #f4f5f7) !important;
    border-color: var(--color-border, rgba(209, 213, 219, 0.9)) !important;
    color: var(--color-text, #111827) !important;
}

:root[data-sw-theme] div.sidebarPanel.advancedSearch div.search,
:root[data-sw-theme] div.sidebarPanel.advancedSearch ul.search li input+label,
:root[data-sw-theme] div.sidebarPanel.advancedSearch ul.search select,
:root[data-sw-theme] div.sidebarPanel.advancedSearch table.search tr,
:root[data-sw-theme] div.sidebarPanel.advancedSearch table.search th,
:root[data-sw-theme] div.sidebarPanel.advancedSearch table.search td,
:root[data-sw-theme] div.sidebarPanel.advancedSearch ul.dynatree-container,
:root[data-sw-theme] div.sidebarPanel.advancedSearch .ui-slider,
:root[data-sw-theme] div.sidebarPanel.advancedSearch .ui-slider-range,
:root[data-sw-theme] div.sidebarPanel.advancedSearch a.greyButton.addFilter {
    background: var(--color-surface-2, #f9fafb) !important;
    border-color: var(--color-border, rgba(226, 232, 240, 0.9)) !important;
    color: var(--color-text, #111827) !important;
}

:root[data-sw-theme] div.sidebarPanel.advancedSearch ul.dynatree-container a,
:root[data-sw-theme] div.sidebarPanel.advancedSearch ul.dynatree-container span {
    color: var(--color-text, #111827) !important;
}
</style>
<?php
