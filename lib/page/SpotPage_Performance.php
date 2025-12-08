<?php

class SpotPage_Performance extends SpotPage_Abs
{
    private $_performanceMonitor;
    private $_performanceEnabled;
    
    public function __construct(Dao_Factory $daoFactory, Services_Settings_Container $settings, array $currentSession, array $params = [])
    {
        parent::__construct($daoFactory, $settings, $currentSession);
        
        $this->_performanceEnabled = false;
        
        // Check if the Performance Monitor class exists
        if (file_exists(__DIR__ . '/../services/Performance/Services_Performance_Monitor.php')) {
            require_once __DIR__ . '/../services/Performance/Services_Performance_Monitor.php';
            
            if (class_exists('Services_Performance_Monitor')) {
                $this->_performanceMonitor = new Services_Performance_Monitor($settings, $daoFactory);
                $this->_performanceEnabled = true;
            }
        }
    }
    
    public function render()
    {
        // Check if the user is logged in and has the correct permissions
        $this->_spotSec->fatalPermCheck(SpotSecurity::spotsec_view_spotdetail, '');
        
        // Generate the performance report if performance monitoring is enabled
        $performanceReport = [];
        if ($this->_performanceEnabled) {
            $performanceReport = $this->_performanceMonitor->generateReport();
        } else {
            // Create a basic report if performance monitoring is not available
            $performanceReport = [
                'metrics' => [
                    'page_load_time' => ['value' => 0, 'unit' => 'ms', 'description' => 'Total page load time'],
                    'database_queries' => ['value' => 0, 'unit' => 'count', 'description' => 'Number of database queries'],
                    'memory_usage' => ['value' => round(memory_get_peak_usage(true) / 1024 / 1024, 2), 'unit' => 'MB', 'description' => 'Memory usage']
                ],
                'trends' => ['dates' => [], 'metrics' => []],
                'timestamp' => time()
            ];
        }
        
        // Set page title
        $this->_pageTitle = 'Performance Dashboard';
        
        // Render the performance dashboard template
        $this->template('performance-dashboard', [
            'performanceReport' => $performanceReport
        ]);
    }
}
