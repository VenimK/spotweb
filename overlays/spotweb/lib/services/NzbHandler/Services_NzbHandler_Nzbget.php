<?php

class Services_NzbHandler_Nzbget extends Services_NzbHandler_abs
{
    private $_host = null;
    private $_timeout = null;
    private $_url = null;
    private $_ssl = null;
    private $_username = null;
    private $_password = null;

    public function __construct(Services_Settings_Container $settings, array $nzbHandling)
    {
        parent::__construct($settings, 'NZBGet', 'NZBGet', $nzbHandling);

        $nzbget = $nzbHandling['nzbget'] ?? [];
        $this->_host = trim((string) ($nzbget['host'] ?? ''));
        $port = trim((string) ($nzbget['port'] ?? '6789'));
        if ($port === '') {
            $port = '6789';
        }
        $this->_timeout = (int) ($nzbget['timeout'] ?? 30);
        if ($this->_timeout < 1) {
            $this->_timeout = 30;
        }

        // Accept true/'on'/1 for SSL (prefs may store checkbox as bool or string)
        $sslRaw = $nzbget['ssl'] ?? false;
        $this->_ssl = ($sslRaw === true || $sslRaw === 1 || $sslRaw === '1' || $sslRaw === 'on');

        $scheme = $this->_ssl ? 'https' : 'http';
        $this->_url = $this->_host !== ''
            ? $scheme.'://'.$this->_host.':'.$port.'/jsonrpc'
            : '';
        $this->_username = (string) ($nzbget['username'] ?? '');
        $this->_password = (string) ($nzbget['password'] ?? '');
    }

    // __construct

    public function processNzb($fullspot, $nzblist)
    {
        $filename = $this->cleanForFileSystem($fullspot['title']).'.nzb';
        // nzbget does not support zip files, must merge
        $nzb = $this->mergeNzbList($nzblist);
        $category = $this->convertCatToSabnzbdCat($fullspot);

        return $this->uploadNzb($filename, $category, false, $nzb);
    }

    // processNzb

    private function sendRequest($method, $args)
    {
        SpotTiming::start(__CLASS__.'::'.__FUNCTION__);
        if ($this->_url === '') {
            throw new Exception('NZBGet host is not configured');
        }

        $reqarr = ['version' => '1.1', 'method' => $method, 'params' => $args];
        $content = json_encode($reqarr);

        /*
         * Actually perform the HTTP POST
         */
        $svcProvHttp = new Services_Providers_Http(null);
        $svcProvHttp->setUsername($this->_username);
        $svcProvHttp->setPassword($this->_password);
        $svcProvHttp->setMethod('POST');
        $svcProvHttp->setContentType('application/json');
        $svcProvHttp->setRawPostData($content);
        if (method_exists($svcProvHttp, 'setTimeout') && $this->_timeout > 0) {
            $svcProvHttp->setTimeout($this->_timeout);
        }
        $output = $svcProvHttp->perform($this->_url, null);

        if ($output['successful'] === false) {
            $errorStr = "ERROR: NZBGet method '".$method."' failed: ".$output['errorstr'];

            error_log($errorStr);

            throw new Exception($errorStr);
        } // if

        $response = json_decode($output['data'], true);
        if (is_array($response) && isset($response['error']) && isset($response['error']['code'])) {
            error_log("NZBGet RPC: Method '".$method."', ".$response['error']['message'].' ('.$response['error']['code'].')');

            throw new Exception("NZBGet RPC: Method '".$method."', ".$response['error']['message'].' ('.$response['error']['code'].')');
        } elseif (is_array($response) && array_key_exists('result', $response)) {
            $response = $response['result'];
        }
        SpotTiming::stop(__CLASS__.'::'.__FUNCTION__, [$method, $args]);

        return $response;
    }

    // sendRequest

    // NzbHandler API functions

    /**
     * Check if handler is available.
     *
     * @return bool
     */
    public function isAvailable()
    {
        if (empty($this->_url) || empty($this->_host)) {
            return false;
        } // if

        try {
            $this->sendRequest('status', []);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /*
     * Return the supported API functions for this NzbHandler imlementation
     */
    public function hasApiSupport()
    {
        // Always advertise status/queue controls; individual methods may still no-op on old versions
        return 'getStatus,pauseQueue,resumeQueue,setSpeedLimit,moveDown,moveUp'
            .',moveTop,moveBottom,setCategory,delete,pause,resume,getVersion,setPriority,rename';
    }

    // hasApiSupport

    /*
     * NZBGet API method: append
     * Add an NZB file to download queue
     */
    public function uploadNzb($filename, $category, $addToTop, $nzb)
    {
        $content = base64_encode($nzb);
        $version = (string) $this->getVersion();

        // NZBGet 13+ uses a different parameter order
        if ($version !== '' && version_compare($version, '13.0', '>=')) {
            // NZBFilename, Content, Category, Priority, AddToTop, AddPaused, DupeKey, DupeScore, DupeMode
            $args = [
                $filename,
                $content,
                (string) $category,
                0,
                (bool) $addToTop,
                false,
                '',
                0,
                'FORCE',
            ];
        } else {
            // Legacy: filename, category, addToTop, content
            $args = [$filename, $category, (bool) $addToTop, $content];
        }

        return $this->sendRequest('append', $args);
    }

    // nzbgetApi_append

    /*
     * NZBGet API method: status + listgroups
     * Normalized to the same queue shape used by SABnzbd panel / dashboard.
     */
    public function getStatus()
    {
        $status = $this->sendRequest('status', []);
        $listgroups = $this->sendRequest('listgroups', []);
        if (!is_array($listgroups)) {
            $listgroups = [];
        }
        if (!is_array($status)) {
            $status = [];
        }

        $result = [];
        $result['queue']['handler'] = 'NZBGet';
        try {
            $result['queue']['version'] = (string) $this->getVersion();
        } catch (Exception $e) {
            $result['queue']['version'] = '';
        }

        $serverPaused = !empty($status['ServerPaused']);
        $downloadPaused = !empty($status['DownloadPaused']);
        $standBy = !empty($status['ServerStandBy']);

        if ($serverPaused || $downloadPaused) {
            $result['queue']['status'] = 'Paused';
        } elseif ($standBy) {
            $result['queue']['status'] = 'Idle';
        } else {
            $result['queue']['status'] = 'Active';
        }

        $result['queue']['paused'] = $serverPaused || $downloadPaused;
        // DownloadLimit is bytes/sec in NZBGet; panel shows KB/s integer like SAB
        $downloadLimit = (int) ($status['DownloadLimit'] ?? 0);
        $result['queue']['speedlimit'] = $downloadLimit > 0 ? (int) round($downloadLimit / 1024) : 0;

        // Free / total disk (GB) — FreeDiskSpaceMB is MiB on modern NZBGet
        if (isset($status['FreeDiskSpaceMB'])) {
            $result['queue']['freediskspace'] = round(((float) $status['FreeDiskSpaceMB']) / 1024, 2);
        } else {
            $result['queue']['freediskspace'] = '-';
        }
        if (isset($status['TotalDiskSpaceMB']) && (float) $status['TotalDiskSpaceMB'] > 0) {
            $result['queue']['totaldiskspace'] = round(((float) $status['TotalDiskSpaceMB']) / 1024, 2);
        } else {
            $result['queue']['totaldiskspace'] = '-';
        }

        $result['queue']['bytepersec'] = (int) ($status['DownloadRate'] ?? 0);
        $result['queue']['mbsize'] = 0;
        $result['queue']['mbremaining'] = (float) ($status['RemainingSizeMB'] ?? 0);
        $result['queue']['uptime'] = (int) ($status['UpTimeSec'] ?? 0);
        $result['queue']['download_time'] = (int) ($status['DownloadTimeSec'] ?? 0);
        $result['queue']['thread_count'] = (int) ($status['ThreadCount'] ?? 0);
        $result['queue']['post_job_count'] = (int) ($status['PostJobCount'] ?? 0);
        $result['queue']['url_count'] = (int) ($status['UrlCount'] ?? 0);
        $result['queue']['server_standby'] = $standBy;

        $secondsremaining = 0;
        $downloadRate = (int) ($status['DownloadRate'] ?? 0);
        if ($downloadRate != 0) {
            if (isset($status['RemainingSizeLo']) && (int) $status['RemainingSizeLo'] < 0) {
                $secondsremaining = ((float) $status['RemainingSizeMB']) / ($downloadRate / 1024 / 1024);
            } elseif (isset($status['RemainingSizeLo'])) {
                $secondsremaining = ((float) $status['RemainingSizeLo']) / $downloadRate;
            } elseif (!empty($status['RemainingSizeMB'])) {
                $secondsremaining = ((float) $status['RemainingSizeMB'] * 1024 * 1024) / $downloadRate;
            }
        }
        $result['queue']['secondsremaining'] = (int) $secondsremaining;

        $downloads = [];
        for ($i = 0; $i < count($listgroups); $i++) {
            $group = $listgroups[$i];
            // Prefer NZBID (modern) then LastID (legacy)
            $id = $group['NZBID'] ?? $group['LastID'] ?? $group['FirstID'] ?? $i;
            $fileSizeMb = (float) ($group['FileSizeMB'] ?? $group['FileSizeLo'] ?? 0);
            if ($fileSizeMb <= 0 && isset($group['FileSizeLo'])) {
                $fileSizeMb = ((float) $group['FileSizeLo']) / 1024 / 1024;
            }
            $remainingMb = (float) ($group['RemainingSizeMB'] ?? 0);

            $downloads[$i]['paused'] = ((int) ($group['PausedSizeLo'] ?? 0) > 0) || !empty($group['PausedSizeMB']);
            $downloads[$i]['id'] = $id;
            $downloads[$i]['filename'] = $group['NZBNicename'] ?? $group['NZBFilename'] ?? ('item-'.$id);
            $downloads[$i]['category'] = $group['Category'] ?? '';
            $downloads[$i]['mbsize'] = $fileSizeMb;
            $downloads[$i]['mbremaining'] = $remainingMb;
            $downloads[$i]['percentage'] = 0;
            if ($fileSizeMb > 0) {
                $downloads[$i]['percentage'] = (int) round((($fileSizeMb - $remainingMb) / $fileSizeMb) * 100);
            }
            $downloads[$i]['status'] = $group['Status'] ?? '';
            $downloads[$i]['priority'] = $group['MaxPriority'] ?? $group['Priority'] ?? 0;

            $result['queue']['mbsize'] = $result['queue']['mbsize'] + $downloads[$i]['mbsize'];
        }

        $result['queue']['slots'] = $downloads;
        $result['queue']['nrofdownloads'] = count($downloads);

        return $result;
    }

    // getStatus

    /*
     * NZBGet API method: pause
     * Pause the download queue
     */
    public function pauseQueue()
    {
        return $this->sendRequest('pause', []);
    }

    //pauseQueue

    /*
     * NZBGet API method: resume
     * Resume the download queue when paused
     */
    public function resumeQueue()
    {
        return $this->sendRequest('resume', []);
    }

    // resumeQueue

    /*
     * NZBGet API method: rate
     * Set the maximum download rate
     */
    public function setSpeedLimit($limit)
    {
        $args = [(int) $limit];

        return $this->sendRequest('rate', $args);
    }

    // setSpeedLimit

    /*
     * NZBGet API method: editqueue
     * Move a download one position down in the queue
     */
    public function moveDown($id)
    {
        $args = ['groupmoveoffset', (int) 1, '', (int) $id];

        return $this->sendRequest('editqueue', $args);
    }

    // moveDown

    /*
     * NZBGet API method: editqueue
     * Move a download one position up in the queue
     */
    public function moveUp($id)
    {
        $args = ['groupmoveoffset', (int) -1, '', (int) $id];

        return $this->sendRequest('editqueue', $args);
    }

    // moveUp

    /*
     * NZBGet API method: editqueue
     * Move a download to the top of the queue
     */
    public function moveTop($id)
    {
        $args = ['groupmovetop', 0, '', (int) $id];

        return $this->sendRequest('editqueue', $args);
    }

    // moveTop

    /*
     * NZBGet API method: editqueue
     * Move a download to the bottom of the queue
     */
    public function moveBottom($id)
    {
        $args = ['groupmovebottom', 0, '', (int) $id];

        return $this->sendRequest('editqueue', $args);
    }

    // moveBottom

    /*
     * NZBGet API method: editqueue
     * Set the category for a download
     */
    public function setCategory($id, $category)
    {
        $args = ['groupsetcategory', (int) 0, $category, (int) $id];

        return $this->sendRequest('editqueue', $args);
    }

    // setCategory

    /*
     * NZBGet API method: editqueue
     * Set the priority for a download
     * Only supported when using NZBGet v0.8.0 (or higher)
     */
    public function setPriority($id, $priority)
    {
        if ($this->getVersion() < '0.8.0') {
            return false;
        }

        // parse integer value a string
        $priority = (string) $priority;
        $args = ['groupsetpriority', (int) 0, $priority, (int) $id];

        return $this->sendRequest('editqueue', $args);
    }

    // setPriority

    /*
     * NZBGet API method: -
     * Not implemented yet. Could be added using the editqueue method and using the
     * GroupSetParameter parameter to set a postprocessing parameter. This would however
     * also require support in the used post-process script.
     */
    public function setPassword($id, $password)
    {
        return false;
    }

    // setPassword

    /*
     * NZBGet API method: editqueue
     * Delete a download from the queue
     */
    public function delete($id)
    {
        $args = ['groupdelete', (int) 0, '', (int) $id];

        return $this->sendRequest('editqueue', $args);
    }

    // delete

    /*
     * NZBGet API method: editqueue
     * Rename a download
     * Only supported when using NZBGet v0.8.0 (or higher)
     */
    public function rename($id, $name)
    {
        if ($this->getVersion() < '0.8.0') {
            return false;
        }

        $name = $this->cleanForFileSystem($name);

        $args = ['groupsetname', (int) 0, $name, (int) $id];

        return $this->sendRequest('editqueue', $args);
    }

    // rename

    /*
     * NZBGet API method: editqueue
     * Pause a download in the queue
     */
    public function pause($id)
    {
        $args = ['grouppause', (int) 0, '', (int) $id];

        return $this->sendRequest('editqueue', $args);
    }

    // pause

    /*
     * NZBGet API method: editqueue
     * Resume a paused download in the queue
     */
    public function resume($id)
    {
        $args = ['groupresume', (int) 0, '', (int) $id];

        return $this->sendRequest('editqueue', $args);
    }

    // resume

    /*
     * NZBGet API method: -
     * Since NZBGet will simply create a category directory if it does not exist yet,
     * NZBGet does not have a fixed list of categories. Therefor we'll use the list of
     * categories defined in SpotWeb.
     * The 'readonly' name/value pair is set to false to allow for a template to offer a
     * free text field so that the user can assign a category name not defined in the
     * category list.
     */
    public function getBuiltinCategories()
    {
        $result = parent::getBuiltinCategories();

        // allow adding of adhoc categories
        $result['readonly'] = false;

        return $result;
    }

    // getCategories

    /*
     * NZBGet API method: version
     * Returns the version of NZBGet
     */
    public function getVersion()
    {
        return $this->sendRequest('version', []);
    }

    // getVersion
} // class Services_NzbHandler_Nzbget
