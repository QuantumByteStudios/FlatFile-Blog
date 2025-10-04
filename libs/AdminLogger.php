<?php
/**
 * Admin Action Logger
 * Logs all admin actions for security and audit purposes
 */

class AdminLogger
{
    private static $log_dir = __DIR__ . '/../logs/';
    private static $log_file = 'admin_actions.log';
    private static $max_log_size = 10 * 1024 * 1024; // 10MB
    private static $max_log_files = 5;
    
    /**
     * Initialize logging system
     */
    public static function init()
    {
        if (!file_exists(self::$log_dir)) {
            mkdir(self::$log_dir, 0755, true);
        }
    }
    
    /**
     * Log admin action
     */
    public static function log($action, $details = [], $user = 'admin')
    {
        self::init();
        
        $log_entry = [
            'timestamp' => date('c'),
            'user' => $user,
            'action' => $action,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'details' => $details
        ];
        
        $log_line = json_encode($log_entry) . "\n";
        $log_path = self::$log_dir . self::$log_file;
        
        // Rotate log if too large
        if (file_exists($log_path) && filesize($log_path) > self::$max_log_size) {
            self::rotateLog();
        }
        
        // Write to log file
        file_put_contents($log_path, $log_line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get recent log entries
     */
    public static function getRecentLogs($limit = 50)
    {
        self::init();
        
        $log_path = self::$log_dir . self::$log_file;
        
        if (!file_exists($log_path)) {
            return [];
        }
        
        $lines = file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];
        
        // Get last $limit lines
        $lines = array_slice($lines, -$limit);
        
        foreach ($lines as $line) {
            $log_entry = json_decode($line, true);
            if ($log_entry) {
                $logs[] = $log_entry;
            }
        }
        
        return array_reverse($logs); // Most recent first
    }
    
    /**
     * Get log statistics
     */
    public static function getStats($days = 30)
    {
        self::init();
        
        $log_path = self::$log_dir . self::$log_file;
        
        if (!file_exists($log_path)) {
            return [
                'total_actions' => 0,
                'actions_by_type' => [],
                'actions_by_user' => [],
                'recent_activity' => []
            ];
        }
        
        $lines = file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats = [
            'total_actions' => 0,
            'actions_by_type' => [],
            'actions_by_user' => [],
            'recent_activity' => []
        ];
        
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($lines as $line) {
            $log_entry = json_decode($line, true);
            if (!$log_entry) continue;
            
            $log_time = strtotime($log_entry['timestamp']);
            if ($log_time < $cutoff_time) continue;
            
            $stats['total_actions']++;
            
            // Count by action type
            $action = $log_entry['action'];
            $stats['actions_by_type'][$action] = ($stats['actions_by_type'][$action] ?? 0) + 1;
            
            // Count by user
            $user = $log_entry['user'];
            $stats['actions_by_user'][$user] = ($stats['actions_by_user'][$user] ?? 0) + 1;
            
            // Recent activity (last 7 days)
            if ($log_time > (time() - (7 * 24 * 60 * 60))) {
                $stats['recent_activity'][] = $log_entry;
            }
        }
        
        // Sort recent activity by timestamp
        usort($stats['recent_activity'], function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return $stats;
    }
    
    /**
     * Rotate log files
     */
    private static function rotateLog()
    {
        $log_path = self::$log_dir . self::$log_file;
        
        // Rotate existing files
        for ($i = self::$max_log_files - 1; $i > 0; $i--) {
            $old_file = $log_path . '.' . $i;
            $new_file = $log_path . '.' . ($i + 1);
            
            if (file_exists($old_file)) {
                if ($i === self::$max_log_files - 1) {
                    unlink($old_file); // Delete oldest
                } else {
                    rename($old_file, $new_file);
                }
            }
        }
        
        // Move current log
        if (file_exists($log_path)) {
            rename($log_path, $log_path . '.1');
        }
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
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    /**
     * Clean old logs
     */
    public static function cleanOldLogs($days = 90)
    {
        self::init();
        
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        $log_files = glob(self::$log_dir . '*.log*');
        
        foreach ($log_files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}
?>
