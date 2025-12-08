<?php
    // Make sure the user is logged in
    $this->includeTemplate('includes/header');
    
    // Ensure performanceReport is available
    if (!isset($performanceReport)) {
        $performanceReport = [
            'metrics' => [],
            'trends' => ['dates' => [], 'metrics' => []],
            'timestamp' => time()
        ];
    }
?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Performance Dashboard</h1>
            <p>This dashboard shows performance metrics for your Spotweb installation.</p>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Current Performance Metrics</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Value</th>
                                <th>Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performanceReport['metrics'] as $name => $metric): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($metric['description']); ?></td>
                                <td><?php echo htmlspecialchars($metric['value']); ?></td>
                                <td><?php echo htmlspecialchars($metric['unit']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-muted">
                    Last updated: <?php echo date('Y-m-d H:i:s', $performanceReport['timestamp']); ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Performance Optimization Status</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Lazy Loading
                            <span class="badge bg-success">Enabled</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Caching
                            <?php if ($settings->get('cache_enabled', false)): ?>
                                <span class="badge bg-success">Enabled (<?php echo htmlspecialchars($settings->get('cache_method', 'file')); ?>)</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Disabled</span>
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Content Prefetching
                            <span class="badge bg-success">Enabled</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Resource Prioritization
                            <span class="badge bg-success">Enabled</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Performance Monitoring
                            <?php if ($settings->get('performance_monitoring_enabled', false)): ?>
                                <span class="badge bg-success">Enabled</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Disabled</span>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($performanceReport['trends']['dates'])): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Performance Trends</h5>
                </div>
                <div class="card-body">
                    <div id="performance-chart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Performance trends data
            const trendsData = <?php echo json_encode($performanceReport['trends']); ?>;
            
            // Create chart if Chart.js is available
            if (typeof Chart !== 'undefined') {
                const ctx = document.getElementById('performance-chart').getContext('2d');
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: trendsData.dates,
                        datasets: [
                            {
                                label: 'Page Load Time (ms)',
                                data: trendsData.metrics.page_load_time.data,
                                borderColor: 'rgb(75, 192, 192)',
                                tension: 0.1
                            },
                            {
                                label: 'Database Time (ms)',
                                data: trendsData.metrics.database_time.data,
                                borderColor: 'rgb(255, 99, 132)',
                                tension: 0.1
                            },
                            {
                                label: 'Memory Usage (MB)',
                                data: trendsData.metrics.memory_usage.data,
                                borderColor: 'rgb(153, 102, 255)',
                                tension: 0.1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            } else {
                document.getElementById('performance-chart').innerHTML = '<p class="text-center">Chart.js is required to display performance trends.</p>';
            }
        });
    </script>
    <?php endif; ?>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Performance Optimization Settings</h5>
                </div>
                <div class="card-body">
                    <form action="?page=editsettings" method="post">
                        <input type="hidden" name="editsettingsform[xsrfid]" value="<?php echo $this->_spotSec->generateXsrfToken('editsettingsform'); ?>">
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="cache_enabled" name="editsettingsform[cache_enabled]" value="1" <?php echo $settings->get('cache_enabled', false) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="cache_enabled">Enable Caching</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cache_method" class="form-label">Cache Method</label>
                            <select class="form-select" id="cache_method" name="editsettingsform[cache_method]">
                                <option value="file" <?php echo $settings->get('cache_method', 'file') == 'file' ? 'selected' : ''; ?>>File</option>
                                <option value="apc" <?php echo $settings->get('cache_method', 'file') == 'apc' ? 'selected' : ''; ?>>APC/APCu</option>
                                <option value="memcached" <?php echo $settings->get('cache_method', 'file') == 'memcached' ? 'selected' : ''; ?>>Memcached</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cache_ttl" class="form-label">Cache TTL (seconds)</label>
                            <input type="number" class="form-control" id="cache_ttl" name="editsettingsform[cache_ttl]" value="<?php echo $settings->get('cache_ttl', 3600); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="performance_monitoring_enabled" name="editsettingsform[performance_monitoring_enabled]" value="1" <?php echo $settings->get('performance_monitoring_enabled', false) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="performance_monitoring_enabled">Enable Performance Monitoring</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    $this->includeTemplate('includes/footer');
?>
