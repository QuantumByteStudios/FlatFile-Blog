<?php

/**
 * FlatFile Blog - Health Check Endpoint
 * System health monitoring and diagnostics
 */

// Define constant to allow access
define('ALLOW_DIRECT_ACCESS', true);

try {
    require_once 'functions.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'System error']);
    exit;
}

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');

class HealthChecker
{
    private static $checks = [];

    /**
     * Add health check
     */
    public static function addCheck($name, $callback)
    {
        self::$checks[$name] = $callback;
    }

    /**
     * Run all health checks
     */
    public static function runChecks()
    {
        $results = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'checks' => [],
            'summary' => [
                'total' => count(self::$checks),
                'passed' => 0,
                'failed' => 0,
                'warnings' => 0
            ]
        ];

        foreach (self::$checks as $name => $callback) {
            try {
                $result = $callback();
                $results['checks'][$name] = $result;

                if ($result['status'] === 'pass') {
                    $results['summary']['passed']++;
                } elseif ($result['status'] === 'fail') {
                    $results['summary']['failed']++;
                    $results['status'] = 'unhealthy';
                } elseif ($result['status'] === 'warn') {
                    $results['summary']['warnings']++;
                    if ($results['status'] === 'healthy') {
                        $results['status'] = 'degraded';
                    }
                }
            } catch (Exception $e) {
                $results['checks'][$name] = [
                    'status' => 'fail',
                    'message' => 'Check failed: ' . $e->getMessage()
                ];
                $results['summary']['failed']++;
                $results['status'] = 'unhealthy';
            }
        }

        return $results;
    }
}

// Define health checks
HealthChecker::addCheck('database_connectivity', function () {
    // Check if content directory is accessible
    if (!is_dir(CONTENT_DIR)) {
        return ['status' => 'fail', 'message' => 'Content directory not accessible'];
    }

    if (!is_writable(CONTENT_DIR)) {
        return ['status' => 'warn', 'message' => 'Content directory not writable'];
    }

    return ['status' => 'pass', 'message' => 'Content directory accessible and writable'];
});

HealthChecker::addCheck('uploads_directory', function () {
    if (!is_dir(UPLOADS_DIR)) {
        return ['status' => 'warn', 'message' => 'Uploads directory not found'];
    }

    if (!is_writable(UPLOADS_DIR)) {
        return ['status' => 'warn', 'message' => 'Uploads directory not writable'];
    }

    return ['status' => 'pass', 'message' => 'Uploads directory accessible and writable'];
});

HealthChecker::addCheck('index_file', function () {
    $index_file = CONTENT_DIR . 'index.json';

    if (!file_exists($index_file)) {
        return ['status' => 'warn', 'message' => 'Index file not found'];
    }

    $index_data = json_decode(file_get_contents($index_file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['status' => 'fail', 'message' => 'Index file corrupted'];
    }

    return ['status' => 'pass', 'message' => 'Index file valid'];
});

HealthChecker::addCheck('disk_space', function () {
    $free_bytes = disk_free_space(__DIR__);
    $total_bytes = disk_total_space(__DIR__);

    if ($free_bytes === false || $total_bytes === false) {
        return ['status' => 'warn', 'message' => 'Cannot determine disk space'];
    }

    $free_gb = round($free_bytes / (1024 * 1024 * 1024), 2);
    $usage_percent = round((($total_bytes - $free_bytes) / $total_bytes) * 100, 2);

    if ($usage_percent > 90) {
        return ['status' => 'fail', 'message' => "Disk usage critical: {$usage_percent}% used"];
    } elseif ($usage_percent > 80) {
        return ['status' => 'warn', 'message' => "Disk usage high: {$usage_percent}% used"];
    }

    return ['status' => 'pass', 'message' => "Disk usage normal: {$usage_percent}% used, {$free_gb}GB free"];
});

HealthChecker::addCheck('php_version', function () {
    $version = PHP_VERSION;
    $major_version = (int) substr($version, 0, 1);

    if ($major_version < 8) {
        return ['status' => 'fail', 'message' => "PHP version too old: {$version} (requires PHP 8+)"];
    }

    return ['status' => 'pass', 'message' => "PHP version: {$version}"];
});

HealthChecker::addCheck('required_extensions', function () {
    $required = ['json', 'mbstring', 'fileinfo'];
    $missing = [];

    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }

    if (!empty($missing)) {
        return ['status' => 'fail', 'message' => 'Missing required extensions: ' . implode(', ', $missing)];
    }

    return ['status' => 'pass', 'message' => 'All required extensions loaded'];
});

HealthChecker::addCheck('posts_count', function () {
    $posts = get_posts(1, 1000, 'published');
    $count = count($posts);

    if ($count === 0) {
        return ['status' => 'warn', 'message' => 'No published posts found'];
    }

    return ['status' => 'pass', 'message' => "Found {$count} published posts"];
});

HealthChecker::addCheck('recent_activity', function () {
    $index_file = CONTENT_DIR . 'index.json';

    if (!file_exists($index_file)) {
        return ['status' => 'warn', 'message' => 'Cannot check recent activity - no index file'];
    }

    $last_modified = filemtime($index_file);
    $hours_since_update = (time() - $last_modified) / 3600;

    if ($hours_since_update > 24) {
        return ['status' => 'warn', 'message' => "No recent activity - last update {$hours_since_update} hours ago"];
    }

    return ['status' => 'pass', 'message' => 'Recent activity detected'];
});

// Run health checks
$health_status = HealthChecker::runChecks();

// Set HTTP status code based on health
if ($health_status['status'] === 'unhealthy') {
    http_response_code(503); // Service Unavailable
} elseif ($health_status['status'] === 'degraded') {
    http_response_code(200); // OK but with warnings
} else {
    http_response_code(200); // OK
}

// Output JSON response
echo json_encode($health_status, JSON_PRETTY_PRINT);
