<?php
/**
 * Security Hardener
 * Advanced security features and hardening
 */

class SecurityHardener
{
    private static $failed_attempts_file = __DIR__ . '/../logs/failed_attempts.json';
    private static $max_attempts = 5;
    private static $lockout_duration = 900; // 15 minutes
    private static $rate_limit_window = 60; // 1 minute
    private static $rate_limit_requests = 30; // 30 requests per minute
    
    /**
     * Initialize security system
     */
    public static function init()
    {
        // Set secure session parameters only if session not started
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            // Only force secure cookies when site is served over HTTPS
            $is_https = (
                (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) ||
                (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
            );
            ini_set('session.cookie_secure', $is_https ? 1 : 0);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
        }
        
        // Disable dangerous functions
        self::disableDangerousFunctions();
        
        // Set security headers
        self::setSecurityHeaders();
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders()
    {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Strict Transport Security (HTTPS only)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https://cdn.jsdelivr.net; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none';";
        header("Content-Security-Policy: {$csp}");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Check for brute force attempts
     */
    public static function checkBruteForce($ip, $username = null)
    {
        $attempts = self::getFailedAttempts();
        $key = $ip . ($username ? '_' . $username : '');
        
        if (isset($attempts[$key])) {
            $attempt_data = $attempts[$key];
            
            // Check if still in lockout period
            if (time() - $attempt_data['last_attempt'] < self::$lockout_duration) {
                $remaining = self::$lockout_duration - (time() - $attempt_data['last_attempt']);
                return [
                    'blocked' => true,
                    'message' => "Too many failed attempts. Try again in {$remaining} seconds.",
                    'remaining' => $remaining
                ];
            }
            
            // Reset if lockout period has passed
            if ($attempt_data['count'] >= self::$max_attempts) {
                unset($attempts[$key]);
                self::saveFailedAttempts($attempts);
            }
        }
        
        return ['blocked' => false];
    }
    
    /**
     * Record failed attempt
     */
    public static function recordFailedAttempt($ip, $username = null)
    {
        $attempts = self::getFailedAttempts();
        $key = $ip . ($username ? '_' . $username : '');
        
        if (!isset($attempts[$key])) {
            $attempts[$key] = ['count' => 0, 'last_attempt' => 0];
        }
        
        $attempts[$key]['count']++;
        $attempts[$key]['last_attempt'] = time();
        
        self::saveFailedAttempts($attempts);
    }
    
    /**
     * Clear failed attempts (on successful login)
     */
    public static function clearFailedAttempts($ip, $username = null)
    {
        $attempts = self::getFailedAttempts();
        $key = $ip . ($username ? '_' . $username : '');
        
        if (isset($attempts[$key])) {
            unset($attempts[$key]);
            self::saveFailedAttempts($attempts);
        }
    }
    
    /**
     * Rate limiting
     */
    public static function checkRateLimit($ip)
    {
        $rate_data = self::getRateLimitData();
        $current_time = time();
        $window_start = $current_time - self::$rate_limit_window;
        
        // Clean old entries
        $rate_data[$ip] = array_filter($rate_data[$ip] ?? [], function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        // Check if over limit
        if (count($rate_data[$ip] ?? []) >= self::$rate_limit_requests) {
            return [
                'blocked' => true,
                'message' => 'Rate limit exceeded. Please slow down.',
                'retry_after' => self::$rate_limit_window
            ];
        }
        
        // Record this request
        $rate_data[$ip][] = $current_time;
        self::saveRateLimitData($rate_data);
        
        return ['blocked' => false];
    }
    
    /**
     * Input sanitization
     */
    public static function sanitizeInput($input, $type = 'string')
    {
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload error: ' . $file['error']];
        }
        
        // Check file size (5MB max)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['valid' => false, 'error' => 'File too large. Maximum size: 5MB'];
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($extension, $allowed_extensions)) {
            return ['valid' => false, 'error' => 'Invalid file extension'];
        }
        
        // Check for malicious content
        if (self::containsMaliciousContent($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'File contains suspicious content'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Check for malicious content
     */
    private static function containsMaliciousContent($file_path)
    {
        $content = file_get_contents($file_path, false, null, 0, 1024);
        
        // Check for PHP tags
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            return true;
        }
        
        // Check for script tags
        if (stripos($content, '<script') !== false) {
            return true;
        }
        
        // Check for executable content
        if (strpos($content, 'eval(') !== false || strpos($content, 'exec(') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate secure random token
     */
    public static function generateSecureToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Hash password with salt
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Disable dangerous functions
     */
    private static function disableDangerousFunctions()
    {
        $dangerous_functions = [
            'exec', 'system', 'shell_exec', 'passthru',
            'eval', 'assert', 'create_function',
            'file_get_contents', 'file_put_contents', 'fopen', 'fwrite',
            'include', 'include_once', 'require', 'require_once'
        ];
        
        foreach ($dangerous_functions as $func) {
            if (function_exists($func)) {
                // Log attempt to use dangerous function
                error_log("Attempted use of dangerous function: {$func}");
            }
        }
    }
    
    /**
     * Get failed attempts
     */
    private static function getFailedAttempts()
    {
        if (!file_exists(self::$failed_attempts_file)) {
            return [];
        }
        
        $data = file_get_contents(self::$failed_attempts_file);
        return json_decode($data, true) ?: [];
    }
    
    /**
     * Save failed attempts
     */
    private static function saveFailedAttempts($attempts)
    {
        $dir = dirname(self::$failed_attempts_file);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents(self::$failed_attempts_file, json_encode($attempts));
    }
    
    /**
     * Get rate limit data
     */
    private static function getRateLimitData()
    {
        $file = __DIR__ . '/../logs/rate_limit.json';
        
        if (!file_exists($file)) {
            return [];
        }
        
        $data = file_get_contents($file);
        return json_decode($data, true) ?: [];
    }
    
    /**
     * Save rate limit data
     */
    private static function saveRateLimitData($data)
    {
        $file = __DIR__ . '/../logs/rate_limit.json';
        $dir = dirname($file);
        
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($file, json_encode($data));
    }
}
?>
