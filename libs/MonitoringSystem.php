<?php
/**
 * Monitoring System
 * Advanced monitoring, alerting, and performance tracking
 */

class MonitoringSystem
{
    private static $metrics_file = __DIR__ . '/../logs/metrics.json';
    private static $alerts_file = __DIR__ . '/../logs/alerts.json';
    private static $performance_file = __DIR__ . '/../logs/performance.log';
    
    /**
     * Track performance metrics
     */
    public static function trackPerformance($operation, $start_time = null)
    {
        if ($start_time === null) {
            $start_time = microtime(true);
        }
        
        $duration = microtime(true) - $start_time;
        $memory_usage = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        
        $metrics = [
            'timestamp' => date('c'),
            'operation' => $operation,
            'duration' => round($duration, 4),
            'memory_usage' => $memory_usage,
            'peak_memory' => $peak_memory,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => self::getClientIP()
        ];
        
        // Log to performance file
        file_put_contents(self::$performance_file, json_encode($metrics) . "\n", FILE_APPEND | LOCK_EX);
        
        // Check for performance issues
        if ($duration > 2.0) {
            self::alert('performance', "Slow operation: {$operation} took {$duration}s");
        }
        
        if ($peak_memory > 64 * 1024 * 1024) { // 64MB
            self::alert('memory', "High memory usage: {$operation} used " . round($peak_memory / 1024 / 1024, 2) . "MB");
        }
        
        return $metrics;
    }
    
    /**
     * Track system health
     */
    public static function trackSystemHealth()
    {
        $health = [
            'timestamp' => date('c'),
            'disk_space' => self::getDiskSpace(),
            'memory_usage' => self::getMemoryUsage(),
            'load_average' => self::getLoadAverage(),
            'php_version' => PHP_VERSION,
            'post_count' => self::getPostCount(),
            'upload_count' => self::getUploadCount(),
            'error_count' => self::getErrorCount()
        ];
        
        // Save metrics
        $metrics = self::getMetrics();
        $metrics['health'][] = $health;
        
        // Keep only last 100 health checks
        if (count($metrics['health']) > 100) {
            $metrics['health'] = array_slice($metrics['health'], -100);
        }
        
        self::saveMetrics($metrics);
        
        // Check for health issues
        if ($health['disk_space'] < 10) { // Less than 10% free
            self::alert('disk', "Low disk space: {$health['disk_space']}% free");
        }
        
        if ($health['memory_usage'] > 90) { // More than 90% memory used
            self::alert('memory', "High memory usage: {$health['memory_usage']}%");
        }
        
        if ($health['load_average'] > 5.0) { // High load average
            self::alert('load', "High system load: {$health['load_average']}");
        }
        
        return $health;
    }
    
    /**
     * Track user activity
     */
    public static function trackUserActivity($action, $details = [])
    {
        $activity = [
            'timestamp' => date('c'),
            'action' => $action,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'details' => $details
        ];
        
        $metrics = self::getMetrics();
        $metrics['activity'][] = $activity;
        
        // Keep only last 1000 activities
        if (count($metrics['activity']) > 1000) {
            $metrics['activity'] = array_slice($metrics['activity'], -1000);
        }
        
        self::saveMetrics($metrics);
        
        // Check for suspicious activity
        if (self::isSuspiciousActivity($activity)) {
            self::alert('security', "Suspicious activity detected: {$action}");
        }
    }
    
    /**
     * Generate alert
     */
    public static function alert($type, $message, $severity = 'warning')
    {
        $alert = [
            'timestamp' => date('c'),
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'ip' => self::getClientIP(),
            'resolved' => false
        ];
        
        $alerts = self::getAlerts();
        $alerts[] = $alert;
        
        // Keep only last 500 alerts
        if (count($alerts) > 500) {
            $alerts = array_slice($alerts, -500);
        }
        
        self::saveAlerts($alerts);
        
        // Send notification if configured
        self::sendNotification($alert);
        
        return $alert;
    }
    
    /**
     * Get system metrics
     */
    public static function getSystemMetrics()
    {
        return [
            'health' => self::trackSystemHealth(),
            'performance' => self::getPerformanceMetrics(),
            'activity' => self::getActivityMetrics(),
            'alerts' => self::getActiveAlerts()
        ];
    }
    
    /**
     * Get performance metrics
     */
    private static function getPerformanceMetrics()
    {
        $metrics = self::getMetrics();
        $performance = $metrics['performance'] ?? [];
        
        if (empty($performance)) {
            return [];
        }
        
        $durations = array_column($performance, 'duration');
        $memory_usage = array_column($performance, 'peak_memory');
        
        return [
            'avg_duration' => round(array_sum($durations) / count($durations), 4),
            'max_duration' => round(max($durations), 4),
            'min_duration' => round(min($durations), 4),
            'avg_memory' => round(array_sum($memory_usage) / count($memory_usage) / 1024 / 1024, 2),
            'max_memory' => round(max($memory_usage) / 1024 / 1024, 2),
            'total_requests' => count($performance)
        ];
    }
    
    /**
     * Get activity metrics
     */
    private static function getActivityMetrics()
    {
        $metrics = self::getMetrics();
        $activities = $metrics['activity'] ?? [];
        
        if (empty($activities)) {
            return [];
        }
        
        $actions = array_count_values(array_column($activities, 'action'));
        $ips = array_count_values(array_column($activities, 'ip'));
        
        return [
            'total_activities' => count($activities),
            'action_counts' => $actions,
            'top_ips' => array_slice($ips, 0, 10, true),
            'unique_ips' => count($ips)
        ];
    }
    
    /**
     * Get active alerts
     */
    private static function getActiveAlerts()
    {
        $alerts = self::getAlerts();
        return array_filter($alerts, function($alert) {
            return !$alert['resolved'];
        });
    }
    
    /**
     * Check for suspicious activity
     */
    private static function isSuspiciousActivity($activity)
    {
        // Check for rapid requests from same IP
        $metrics = self::getMetrics();
        $recent_activities = array_filter($metrics['activity'] ?? [], function($a) {
            return strtotime($a['timestamp']) > (time() - 60); // Last minute
        });
        
        $same_ip_count = count(array_filter($recent_activities, function($a) use ($activity) {
            return $a['ip'] === $activity['ip'];
        }));
        
        if ($same_ip_count > 30) { // More than 30 requests per minute
            return true;
        }
        
        // Check for suspicious user agents
        $suspicious_agents = ['bot', 'crawler', 'scanner', 'hack'];
        $user_agent = strtolower($activity['user_agent']);
        
        foreach ($suspicious_agents as $agent) {
            if (strpos($user_agent, $agent) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Send notification
     */
    private static function sendNotification($alert)
    {
        // Check if email notifications are enabled
        if (!defined('EMAIL_ENABLED') || !EMAIL_ENABLED) {
            return;
        }
        
        $subject = "Blog Alert: {$alert['type']} - {$alert['severity']}";
        $message = "Alert: {$alert['message']}\n";
        $message .= "Time: {$alert['timestamp']}\n";
        $message .= "IP: {$alert['ip']}\n";
        $message .= "Severity: {$alert['severity']}\n";
        
        // Send email (implement your email sending logic)
        // mail(ADMIN_EMAIL, $subject, $message);
    }
    
    /**
     * Get disk space percentage
     */
    private static function getDiskSpace()
    {
        $bytes = disk_free_space(__DIR__);
        $total = disk_total_space(__DIR__);
        
        if ($total === false || $bytes === false) {
            return 0;
        }
        
        return round(($bytes / $total) * 100, 2);
    }
    
    /**
     * Get memory usage percentage
     */
    private static function getMemoryUsage()
    {
        $memory_usage = memory_get_usage(true);
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit === '-1') {
            return 0; // No limit
        }
        
        $limit_bytes = self::convertToBytes($memory_limit);
        return round(($memory_usage / $limit_bytes) * 100, 2);
    }
    
    /**
     * Get system load average
     */
    private static function getLoadAverage()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0]; // 1-minute load average
        }
        
        return 0;
    }
    
    /**
     * Get post count
     */
    private static function getPostCount()
    {
        if (file_exists(CONTENT_DIR . 'index.json')) {
            $index = json_decode(file_get_contents(CONTENT_DIR . 'index.json'), true);
            return count($index);
        }
        
        return 0;
    }
    
    /**
     * Get upload count
     */
    private static function getUploadCount()
    {
        if (!is_dir(UPLOADS_DIR)) {
            return 0;
        }
        
        $files = glob(UPLOADS_DIR . '**/*', GLOB_BRACE);
        return count($files);
    }
    
    /**
     * Get error count from logs
     */
    private static function getErrorCount()
    {
        $error_log = LOG_DIR . 'php_errors.log';
        
        if (!file_exists($error_log)) {
            return 0;
        }
        
        $content = file_get_contents($error_log);
        return substr_count($content, 'PHP Error');
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIP()
    {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Convert memory limit to bytes
     */
    private static function convertToBytes($value)
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Get metrics
     */
    private static function getMetrics()
    {
        if (!file_exists(self::$metrics_file)) {
            return ['health' => [], 'performance' => [], 'activity' => []];
        }
        
        $data = file_get_contents(self::$metrics_file);
        return json_decode($data, true) ?: ['health' => [], 'performance' => [], 'activity' => []];
    }
    
    /**
     * Save metrics
     */
    private static function saveMetrics($metrics)
    {
        file_put_contents(self::$metrics_file, json_encode($metrics), LOCK_EX);
    }
    
    /**
     * Get alerts
     */
    private static function getAlerts()
    {
        if (!file_exists(self::$alerts_file)) {
            return [];
        }
        
        $data = file_get_contents(self::$alerts_file);
        return json_decode($data, true) ?: [];
    }
    
    /**
     * Save alerts
     */
    private static function saveAlerts($alerts)
    {
        file_put_contents(self::$alerts_file, json_encode($alerts), LOCK_EX);
    }
}
?>
