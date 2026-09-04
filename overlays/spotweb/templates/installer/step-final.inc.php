<table summary="PHP settings">
    <tr>
        <th colspan='2'> Installation succesful</th>
    </tr>
    <tr>
        <td colspan='2'> Spotweb has been installed successfully!</td>
    </tr>
    <tr>
        <td colspan='2'> &nbsp; </td>
    </tr>
    <?php if (!$createdDbSettings) { ?>
        <tr>
            <td> &rarr; </td>
            <td>
                You need to create a textfile with the database settings in it. Please copy & paste the below
                exactly in a file called <i>dbsettings.inc.php</i>.
							<pre><?php echo htmlspecialchars($dbConnectionString); ?>
							</pre>
            </td>
        </tr>
    <?php } ?>
    <tr>
        <td> &rarr; </td>
        <td>
            Spotweb retrieves its information from the newsservers, this is called "retrieving" or retrieval of Spots.
            You need to schedule a retrieval job to run <i>retrieve.php</i> on a regular basis. The first time retrieval
            is run this can take up to several hours before completion.
        </td>
    </tr>
    <tr>
        <td colspan="2"> &nbsp; </td>
    </tr>
    <tr>
        <td> &rarr; </td>
        <td>
            <a href="<?php echo dirname($_SERVER['PHP_SELF']); ?>">Visit your Spotweb</a>
        </td>
    </tr>
<?php
$filterManager = dirname(__DIR__, 2).'/custom/tools/filter-manager.php';
if (is_file($filterManager)) {
    $filterManagerUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/').'/custom/tools/filter-manager.php';
    ?>
    <tr>
        <td colspan="2"> &nbsp; </td>
    </tr>
    <tr>
        <td> &rarr; </td>
        <td>
            Filter Manager is included with this install.
            Open it from the <strong>Filters</strong> toolbar button, from Advanced Search, or
            <a href="<?php echo htmlspecialchars($filterManagerUrl, ENT_QUOTES, 'UTF-8'); ?>">open Filter Manager</a>.
        </td>
    </tr>
<?php } ?>
</table>

<?php echo '<!-- '.$dbCreateOutput.' -->'; ?>
