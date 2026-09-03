	</div>

    <?php if ($tplHelper->allowed(SpotSecurity::spotsec_view_statics, '')) { ?>
        <script type='text/javascript'>
            // Define some global variables showing or hiding specific parts of the UI
            // based on users' security rights
            var spotweb_security_allow_spotdetail = <?php echo (int) $tplHelper->allowed(SpotSecurity::spotsec_view_spotdetail, ''); ?>;
            var spotweb_security_allow_view_spotimage = <?php echo (int) $tplHelper->allowed(SpotSecurity::spotsec_view_spotimage, ''); ?>;
            var spotweb_security_allow_view_comments = <?php echo (int) $tplHelper->allowed(SpotSecurity::spotsec_view_comments, ''); ?>;
            var spotweb_currentfilter_params = "<?php echo str_replace('&amp;', '&', $tplHelper->convertFilterToQueryParams()); ?>";
            var spotweb_retrieve_commentsperpage = <?php if ($settings->get('retrieve_full_comments')) {
    echo 250;
} else {
    echo 10;
} ?>;
            var spotweb_nzbhandler_type = '<?php echo $tplHelper->getNzbHandlerType(); ?>';
            var spotweb_theme_asset_base = '<?php echo $tplHelper->getThemeAssetPath(); ?>';
<?php
    $puCanRetrieve = (
        $currentSession['user']['userid'] > SPOTWEB_ADMIN_USERID &&
        $tplHelper->allowed(SpotSecurity::spotsec_retrieve_spots, '') &&
        $tplHelper->allowed(SpotSecurity::spotsec_consume_api, '')
    );
    $puRetrieveUrl = $puCanRetrieve ? $tplHelper->makeRetrieveUrl() : '';
    $puPrefsUrl = $tplHelper->allowed(SpotSecurity::spotsec_edit_own_userprefs, '')
        ? $tplHelper->makeEditUserPrefsUrl($currentSession['user']['userid'])
        : '';
    $puSettingsUrl = ($tplHelper->allowed(SpotSecurity::spotsec_edit_settings, '') || $tplHelper->allowed(SpotSecurity::spotsec_view_spotweb_updates, ''))
        ? '?page=editsettings'
        : '';
    $puMarkReadUrl = $tplHelper->allowed(SpotSecurity::spotsec_keep_own_seenlist, '')
        ? $tplHelper->getPageUrl('markallasread')
        : '';
    $puWatchlistUrl = ($tplHelper->allowed(SpotSecurity::spotsec_keep_own_watchlist, '') && !empty($currentSession['user']['prefs']['keep_watchlist']))
        ? $tplHelper->getPageUrl('index').'&search[tree]=&search[unfiltered]=true&search[value][]=Watch:0'
        : '';
    $puConfig = [
        'canRetrieve'  => (bool) $puCanRetrieve,
        'retrieveUrl'  => html_entity_decode($puRetrieveUrl, ENT_QUOTES, 'UTF-8'),
        'prefsUrl'     => html_entity_decode($puPrefsUrl, ENT_QUOTES, 'UTF-8'),
        'settingsUrl'  => $puSettingsUrl,
        'markReadUrl'  => html_entity_decode($puMarkReadUrl, ENT_QUOTES, 'UTF-8'),
        'watchlistUrl' => html_entity_decode($puWatchlistUrl, ENT_QUOTES, 'UTF-8'),
        'homeUrl'      => html_entity_decode($tplHelper->makeBaseUrl('path'), ENT_QUOTES, 'UTF-8'),
        'themeToggle'  => true,
    ];
?>
            window.spotwebPowerUx = <?php echo json_encode($puConfig, JSON_UNESCAPED_SLASHES); ?>;
        </script>
        <script src='?page=statics&amp;type=js&amp;lang=<?php echo urlencode($currentSession['user']['prefs']['user_language']); ?>&amp;mod=<?php echo $tplHelper->getStaticModTime('js'); ?>' type='text/javascript'></script>

        <script type='text/javascript'>
            <?php echo "initSpotwebJs('"; echo _('Between '); echo "','"; echo _(' and '); echo "')" ?>;
            //initSpotwebJs();
            <?php if (!empty($toRunJsCode)) {
    echo $toRunJsCode;
} // if
            ?>
        </script>
    <?php } ?>

	</body>
</html>
