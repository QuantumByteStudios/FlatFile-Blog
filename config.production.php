<?php

/**
 * Production Configuration Template
 * Copy this to config.php and customize for your production environment
 */

// Site settings
define('SITE_TITLE', 'Your Blog Title');
define('BASE_URL', 'https://yoursite.com/blog/'); // Change to your domain
define('TIMEZONE', 'UTC'); // Set your timezone

// Directory settings
define('POSTS_DIR', __DIR__ . '/content/posts/');
define('UPLOADS_DIR', __DIR__ . '/uploads/');
define('CONTENT_DIR', __DIR__ . '/content/');
define('CACHE_DIR', __DIR__ . '/cache/');
define('LOG_DIR', __DIR__ . '/logs/');

// Security settings - CHANGE THESE IN PRODUCTION!
define('ADMIN_PASSWORD_HASH', '$2y$10$YourGeneratedHashHere'); // Generate with: password_hash('YourStrongPassword', PASSWORD_DEFAULT)
define('SESSION_LIFETIME', 3600); // 1 hour
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// Performance settings
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600); // 1 hour
define('OPCACHE_ENABLED', true);

// Security hardening
define('RATE_LIMIT_ENABLED', true);
define('BRUTE_FORCE_PROTECTION', true);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// Monitoring and logging
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('ERROR_REPORTING', E_ALL & ~E_DEPRECATED & ~E_STRICT);
define('LOG_ADMIN_ACTIONS', true);
define('LOG_PERFORMANCE', true);

// Backup settings
define('BACKUP_ENABLED', true);
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_STORAGE', 'local'); // local, s3, ftp
define('BACKUP_S3_BUCKET', '');
define('BACKUP_S3_REGION', 'us-east-1');

// Database settings (if using SQLite for scaling)
define('DB_ENABLED', false);
define('DB_PATH', __DIR__ . '/data/blog.db');

// CDN settings (optional)
define('CDN_ENABLED', false);
define('CDN_URL', 'https://cdn.yoursite.com/');

// Email settings (for notifications)
define('EMAIL_ENABLED', false);
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM', 'noreply@yoursite.com');

// API settings (for headless usage)
define('API_ENABLED', false);
define('API_KEY', ''); // Generate secure API key

// Ensure directories exist
function ensure_dirs()
{
    $dirs = [
        POSTS_DIR,
        UPLOADS_DIR,
        CONTENT_DIR . 'backups/',
        CONTENT_DIR . 'logs/',
        CACHE_DIR,
        LOG_DIR,
        __DIR__ . '/libs/',
        __DIR__ . '/tools/'
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// Call ensure_dirs once to make sure all necessary directories are present
ensure_dirs();

// Set default timezone
date_default_timezone_set(TIMEZONE);

// Security headers (if not set by web server)
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Error reporting based on environment
if (defined('LOG_LEVEL') && LOG_LEVEL === 'DEBUG') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(ERROR_REPORTING);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . 'php_errors.log');
}

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// File upload security
ini_set('file_uploads', 1);
ini_set('upload_max_filesize', MAX_UPLOAD_SIZE);
ini_set('post_max_size', MAX_UPLOAD_SIZE * 2);
ini_set('max_execution_time', 30);
ini_set('memory_limit', '128M');

// Disable dangerous functions in production
if (function_exists('ini_set')) {
    ini_set('expose_php', 0);
    ini_set('allow_url_fopen', 0);
    ini_set('allow_url_include', 0);
}

// Initialize security system
if (file_exists(__DIR__ . '/libs/SecurityHardener.php')) {
    require_once __DIR__ . '/libs/SecurityHardener.php';
    SecurityHardener::init();
}
