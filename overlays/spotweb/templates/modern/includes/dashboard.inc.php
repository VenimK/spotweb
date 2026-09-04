<?php
/**
 * Compact home dashboard strip for the modern theme.
 * Expects: $tplHelper, $settings, $currentSession, $daoFactory (optional via $tplHelper).
 */
if (!isset($tplHelper) || !isset($currentSession)) {
    return;
}

// Only show on main index-style pages (not login/error dialogs)
$pageName = strtolower((string) ($_GET['page'] ?? 'index'));
if ($pageName !== '' && $pageName !== 'index') {
    return;
}

$lastRetrieveTs = 0;
$spotTotal = null;
$retrieveRunning = false;
try {
    $lastRetrieveTs = (int) $tplHelper->getLastSpotUpdates();
} catch (Throwable $e) {
    $lastRetrieveTs = 0;
}

try {
    if (method_exists($tplHelper, 'getTotalSpotCount')) {
        $spotTotal = $tplHelper->getTotalSpotCount();
    }
} catch (Throwable $e) {
    $spotTotal = null;
}

$lastLabel = _('Never');
$lastExact = '';
$retrieveClass = 'sw-dash-warn';
if ($lastRetrieveTs > 0) {
    $ageH = (time() - $lastRetrieveTs) / 3600;
    $lastLabel = $tplHelper->formatDate($lastRetrieveTs, 'lastupdate');
    $lastExact = $tplHelper->formatDate($lastRetrieveTs, 'force_lastupdate');
    if ($ageH <= 6) {
        $retrieveClass = 'sw-dash-ok';
    } elseif ($ageH <= 24) {
        $retrieveClass = 'sw-dash-warn';
    } else {
        $retrieveClass = 'sw-dash-fail';
    }
}

$newSpotCount = null;
try {
    if (!empty($currentSession['user']['prefs']['count_newspots'])) {
        $newFilterUrl = $tplHelper->getPageUrl('index').'&amp;search[tree]=&amp;search[unfiltered]=true&amp;search[value][]=New:0';
        $counted = (int) $tplHelper->getNewCountForFilter($newFilterUrl);
        if ($counted >= 0) {
            $newSpotCount = $counted;
        }
    }
} catch (Throwable $e) {
    $newSpotCount = null;
}

$pageSpotCount = (isset($spots) && is_array($spots)) ? count($spots) : null;

$nzbAction = $currentSession['user']['prefs']['nzbhandling']['action'] ?? 'disable';
$nzbConfigured = ($nzbAction && $nzbAction !== 'disable');
$nzbClass = $nzbConfigured ? 'sw-dash-ok' : 'sw-dash-warn';
$nzbLabels = [
    'save'           => _('Save to disk'),
    'push-sabnzbd'   => 'SABnzbd',
    'client-sabnzbd' => 'SABnzbd (browser)',
    'nzbget'         => 'NZBGet',
    'nzbvortex'      => 'NZBVortex',
    'runcommand'     => _('Run command'),
    'disable'        => _('Not configured'),
];
$nzbLabel = $nzbLabels[$nzbAction] ?? htmlspecialchars((string) $nzbAction, ENT_QUOTES, 'UTF-8');
if ($nzbAction === 'save' && !empty($currentSession['user']['prefs']['nzbhandling']['local_dir'])) {
    $nzbLabel .= ' ('.htmlspecialchars(basename((string) $currentSession['user']['prefs']['nzbhandling']['local_dir']), ENT_QUOTES, 'UTF-8').')';
}
if ($nzbAction === 'nzbget') {
    $ng = $currentSession['user']['prefs']['nzbhandling']['nzbget'] ?? [];
    $host = trim((string) ($ng['host'] ?? ''));
    $port = trim((string) ($ng['port'] ?? '6789'));
    if ($host !== '') {
        $nzbLabel .= ' @ '.htmlspecialchars($host.':'.$port, ENT_QUOTES, 'UTF-8');
    }
}
$nzbHasApi = false;
try {
    $nzbHasApi = ($tplHelper->getNzbHandlerApiSupport() !== false)
        && $tplHelper->allowed(SpotSecurity::spotsec_use_sabapi, '');
} catch (Throwable $e) {
    $nzbHasApi = false;
}

$canRetrieve = (
    $currentSession['user']['userid'] > SPOTWEB_ADMIN_USERID &&
    $tplHelper->allowed(SpotSecurity::spotsec_retrieve_spots, '') &&
    $tplHelper->allowed(SpotSecurity::spotsec_consume_api, '')
);
$retrieveUrl = $canRetrieve ? $tplHelper->makeRetrieveUrl() : '';

$newUrl = $tplHelper->getPageUrl('index').'&amp;search[value][]=New:0';
$prefsUrl = $tplHelper->allowed(SpotSecurity::spotsec_edit_own_userprefs, '')
    ? $tplHelper->makeEditUserPrefsUrl($currentSession['user']['userid'])
    : '';
?>
<div class="sw-dashboard" role="region" aria-label="<?php echo htmlspecialchars(_('Dashboard'), ENT_QUOTES, 'UTF-8'); ?>">
  <div class="sw-dash-item">
    <span class="sw-dash-label"><?php echo _('Last retrieve'); ?></span>
    <span class="sw-dash-value <?php echo $retrieveClass; ?>" title="<?php echo htmlspecialchars((string) $lastExact, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $lastLabel, ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

<?php if ($newSpotCount !== null) { ?>
  <div class="sw-dash-item">
    <span class="sw-dash-label"><?php echo _('New'); ?></span>
    <span class="sw-dash-value<?php echo $newSpotCount > 0 ? ' sw-dash-ok' : ''; ?>"><?php echo number_format((int) $newSpotCount); ?></span>
  </div>
<?php } ?>

<?php if ($spotTotal !== null) { ?>
  <div class="sw-dash-item">
    <span class="sw-dash-label"><?php echo _('Spots'); ?></span>
    <span class="sw-dash-value"><?php echo number_format((int) $spotTotal); ?><?php
    if ($pageSpotCount !== null && $pageSpotCount > 0) {
        echo ' <span class="sw-dash-sub">('.number_format((int) $pageSpotCount).' '._('on page').')</span>';
    }
    ?></span>
  </div>
<?php } ?>

  <div class="sw-dash-item" data-sw-filter-wrap hidden>
    <span class="sw-dash-label"><?php echo _('Filter'); ?></span>
    <span class="sw-dash-value" data-sw-filter></span>
  </div>

  <div class="sw-dash-item">
    <span class="sw-dash-label"><?php echo _('NZB handler'); ?></span>
    <span class="sw-dash-value <?php echo $nzbClass; ?>"><?php echo $nzbLabel; ?></span>
  </div>

<?php if ($nzbHasApi) { ?>
  <button type="button" class="sw-dash-item sw-dash-dl" data-sw-dl-live="1" data-sw-action="dlpanel" title="<?php echo htmlspecialchars(sprintf(_('Open %s panel'), $tplHelper->getNzbHandlerName()), ENT_QUOTES, 'UTF-8'); ?>">
    <span class="sw-dash-label"><?php echo $nzbAction === 'nzbget' ? 'NZBGet' : htmlspecialchars($tplHelper->getNzbHandlerName(), ENT_QUOTES, 'UTF-8'); ?></span>
    <span class="sw-dash-value sw-dl-status">…</span>
    <span class="sw-dash-value sw-dl-speed"></span>
    <span class="sw-dash-value sw-dl-queue"></span>
    <span class="sw-dash-value sw-dl-eta"></span>
    <span class="sw-dash-value sw-dl-disk"></span>
  </button>
<?php } ?>

  <div class="sw-dash-item">
    <span class="sw-dash-label"><?php echo _('User'); ?></span>
    <span class="sw-dash-value"><?php echo htmlspecialchars((string) $currentSession['user']['username'], ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <div class="sw-dash-actions">
<?php if ($canRetrieve && $retrieveUrl !== '') { ?>
    <a href="<?php echo $retrieveUrl; ?>" class="sw-dash-btn" data-sw-action="retrieve" title="<?php echo htmlspecialchars(_('Retrieve new spots'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo _('Retrieve'); ?> <kbd>r</kbd></a>
<?php } ?>
    <a href="<?php echo $newUrl; ?>" class="sw-dash-btn"><?php echo _('New spots'); ?></a>
    <a href="<?php echo htmlspecialchars($tplHelper->makeFilterManagerUrl(), ENT_QUOTES, 'UTF-8'); ?>" class="sw-dash-btn" target="_blank" rel="noopener" title="<?php echo htmlspecialchars(_('Open the filter manager'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo _('Filters'); ?></a>
<?php if ($nzbHasApi) { ?>
    <button type="button" class="sw-dash-btn" data-sw-action="dlpanel" title="<?php echo htmlspecialchars(sprintf(_('Open %s panel'), $tplHelper->getNzbHandlerName()), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tplHelper->getNzbHandlerName(), ENT_QUOTES, 'UTF-8'); ?></button>
<?php } elseif ($prefsUrl !== '' && !$nzbConfigured) { ?>
    <a href="<?php echo $prefsUrl; ?>" class="sw-dash-btn"><?php echo _('Configure NZB'); ?></a>
<?php } elseif ($prefsUrl !== '' && $nzbAction === 'save') { ?>
    <a href="<?php echo $prefsUrl; ?>" class="sw-dash-btn" title="<?php echo htmlspecialchars(_('Use NZBGet or SABnzbd for live queue info'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo _('Use NZBGet…'); ?></a>
<?php } ?>
    <button type="button" class="sw-dash-btn" data-sw-action="cmdk" title="Command palette"><?php echo _('Commands'); ?> <kbd><?php echo (strpos((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 'Mac') !== false) ? '⌘K' : 'Ctrl+K'; ?></kbd></button>
    <button type="button" class="sw-dash-btn" data-sw-action="help" title="Keyboard shortcuts">?</button>
  </div>
</div>
