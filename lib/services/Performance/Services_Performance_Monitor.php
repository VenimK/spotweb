<?php

/**
 * Services_Performance_Monitor
 *
 * Monitors and reports on Spotweb performance metrics
 */
class Services_Performance_Monitor
{
    private $_settings;
    private $_daoFactory;
    private $_metrics = [];
    private $_startTimes = [];
    private $_enabled;
    
    /**
     * Constructor
     *
     * @param Services_Settings_Container $settings
     * @param Dao_Factory $daoFactory
     */
    public function __construct(Services_Settings_Container $settings, Dao_Factory $daoFactory)
    {
        $this->_settings = $settings;
        $this->_daoFactory = $daoFactory;
        $this->_enabled = $settings->get('performance_monitoring_enabled', false);
        
        // Initialize metrics
        $this->initMetrics();
    }
    
    /**
     * Initialize metrics
     */
    private function initMetrics()
    {
        $this->_metrics = [
            'page_load_time' => [
                'value' => 0,
                'unit' => 'ms',
                'description' => 'Total page load time'
            ],
            'database_queries' => [
                'value' => 0,
                'unit' => 'count',
                'description' => 'Number of database queries'
            ],
            'database_time' => [
                'value' => 0,
                'unit' => 'ms',
                'description' => 'Total database query time'
            ],
            'memory_usage' => [
                'value' => 0,
                'unit' => 'MB',
                'description' => 'Memory usage'
            ],
            'cache_hits' => [
                'value' => 0,
                'unit' => 'count',
                'description' => 'Cache hits'
            ],
            'cache_misses' => [
                'value' => 0,
                'unit' => 'count',
                'description' => 'Cache misses'
            ],
            'spots_retrieved' => [
                'value' => 0,
                'unit' => 'count',
                'description' => 'Number of spots retrieved'
            ]
        ];
    }
    
    /**
     * Start timing a metric
     *
     * @param string $name
     */
    public function startTiming($name)
    {
        if (!$this->_enabled) {
            return;
        }
        
        $this->_startTimes[$name] = microtime(true);
    }
    
    /**
     * Stop timing a metric and record the duration
     *
     * @param string $name
     */
    public function stopTiming($name)
    {
        if (!$this->_enabled || !isset($this->_startTimes[$name])) {
            return;
        }
        
        $duration = (microtime(true) - $this->_startTimes[$name]) * 1000; // Convert to milliseconds
        
        if ($name === 'page_load') {
            $this->setMetric('page_load_time', $duration);
        } elseif (strpos($name, 'database_') === 0) {
            $this->incrementMetric('database_time', $duration);
        }
        
        unset($this->_startTimes[$name]);
    }
    
    /**
     * Set a metric value
     *
     * @param string $name
     * @param mixed $value
     */
    public function setMetric($name, $value)
    {
        if (!$this->_enabled || !isset($this->_metrics[$name])) {
            return;
        }
        
        $this->_metrics[$name]['value'] = $value;
    }
    
    /**
     * Increment a metric value
     *
     * @param string $name
     * @param mixed $value
     */
    public function incrementMetric($name, $value = 1)
    {
        if (!$this->_enabled || !isset($this->_metrics[$name])) {
            return;
        }
        
        $this->_metrics[$name]['value'] += $value;
    }
    
    /**
     * Record a database query
     *
     * @param float $duration Duration in seconds
     */
    public function recordDatabaseQuery($duration)
    {
        if (!$this->_enabled) {
            return;
        }
        
        $this->incrementMetric('database_queries');
        $this->incrementMetric('database_time', $duration * 1000); // Convert to milliseconds
    }
    
    /**
     * Record a cache hit or miss
     *
     * @param bool $hit True for hit, false for miss
     */
    public function recordCacheAccess($hit)
    {
        if (!$this->_enabled) {
            return;
        }
        
        if ($hit) {
            $this->incrementMetric('cache_hits');
        } else {
            $this->incrementMetric('cache_misses');
        }
    }
    
    /**
     * Record spots retrieved
     *
     * @param int $count
     */
    public function recordSpotsRetrieved($count)
    {
        if (!$this->_enabled) {
            return;
        }
        
        $this->incrementMetric('spots_retrieved', $count);
    }
    
    /**
     * Update memory usage metric
     */
    public function updateMemoryUsage()
    {
        if (!$this->_enabled) {
            return;
        }
        
        $memoryUsage = memory_get_peak_usage(true) / 1024 / 1024; // Convert to MB
        $this->setMetric('memory_usage', round($memoryUsage, 2));
    }
    
    /**
     * Generate a performance report
     *
     * @return array
     */
    public function generateReport()
    {
        $this->updateMemoryUsage();
        
        // Get historical data
        $historicalData = $this->getHistoricalData();
        
        // Calculate trends
        $trends = $this->calculateTrends($historicalData);
        
        // Save current metrics for historical data
        $this->saveMetricsHistory();
        
        return [
            'metrics' => $this->_metrics,
            'trends' => $trends,
            'history' => $historicalData,
            'timestamp' => time()
        ];
    }
    
    /**
     * Get historical performance data
     *
     * @return array
     */
    private function getHistoricalData()
    {
        $historyFile = __DIR__ . '/../../../cache/performance_history.json';
        
        if (!file_exists($historyFile)) {
            return [];
        }
        
        $history = json_decode(file_get_contents($historyFile), true);
        
        if (!is_array($history)) {
            return [];
        }
        
        // Keep only the last 30 days of data
        $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
        $history = array_filter($history, function($entry) use ($thirtyDaysAgo) {
            return $entry['timestamp'] >= $thirtyDaysAgo;
        });
        
        return $history;
    }
    
    /**
     * Save current metrics to history
     */
    private function saveMetricsHistory()
    {
        if (!$this->_enabled) {
            return;
        }
        
        $historyFile = __DIR__ . '/../../../cache/performance_history.json';
        $history = $this->getHistoricalData();
        
        // Add current metrics
        $history[] = [
            'metrics' => $this->_metrics,
            'timestamp' => time(),
            'date' => date('Y-m-d')
        ];
        
        // Ensure cache directory exists
        $cacheDir = __DIR__ . '/../../../cache';
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        // Save history
        file_put_contents($historyFile, json_encode($history));
    }
    
    /**
     * Calculate trends based on historical data
     *
     * @param array $history
     * @return array
     */
    private function calculateTrends($history)
    {
        $trends = [];
        
        if (empty($history)) {
            return $trends;
        }
        
        // Group history by day
        $dailyData = [];
        foreach ($history as $entry) {
            $date = isset($entry['date']) ? $entry['date'] : date('Y-m-d', $entry['timestamp']);
            
            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [
                    'count' => 0,
                    'metrics' => []
                ];
            }
            
            $dailyData[$date]['count']++;
            
            foreach ($entry['metrics'] as $name => $metric) {
                if (!isset($dailyData[$date]['metrics'][$name])) {
                    $dailyData[$date]['metrics'][$name] = 0;
                }
                
                $dailyData[$date]['metrics'][$name] += $metric['value'];
            }
        }
        
        // Calculate daily averages
        $dailyAverages = [];
        foreach ($dailyData as $date => $data) {
            $dailyAverages[$date] = [];
            
            foreach ($data['metrics'] as $name => $total) {
                $dailyAverages[$date][$name] = $total / $data['count'];
            }
        }
        
        // Sort by date
        ksort($dailyAverages);
        
        // Format for chart display
        $trends = [
            'dates' => array_keys($dailyAverages),
            'metrics' => []
        ];
        
        foreach ($this->_metrics as $name => $metric) {
            $trends['metrics'][$name] = [
                'label' => $metric['description'],
                'unit' => $metric['unit'],
                'data' => []
            ];
            
            foreach ($dailyAverages as $values) {
                $trends['metrics'][$name]['data'][] = isset($values[$name]) ? $values[$name] : 0;
            }
        }
        
        return $trends;
    }
}
