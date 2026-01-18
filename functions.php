<?php

/**
 * FlatFile Blog Functions
 * Core functionality for the blog system
 */

// Direct access protection is handled by .htaccess file

// Load configuration (robust path resolution for nested routes like /admin/)
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    if (basename($_SERVER['PHP_SELF']) === 'install.php') {
        return; // Allow install.php to run without config
    }
    die('Configuration file not found. Please run install.php first.');
}

try {
    require_once $configPath;
} catch (Exception $e) {
    error_log('Error loading config.php: ' . $e->getMessage());
    die('Configuration error. Please check your installation.');
}

/**
 * Load blog settings from JSON file
 */
function load_settings()
{
    $settings_file = CONTENT_DIR . 'settings.json';

    if (!file_exists($settings_file)) {
        return [
            'site_title' => 'My FlatFile Blog',
            'site_description' => 'A simple, fast, and secure flat-file blog system.',
            'posts_per_page' => 10,
            'timezone' => 'UTC',
            'theme' => 'default',
            'allow_comments' => true,
            'moderate_comments' => false,
            'social_links' => [],
            'analytics_code' => '',
            'maintenance_mode' => false
        ];
    }

    $settings = json_decode(file_get_contents($settings_file), true);
    return $settings ?: [];
}

/**
 * Save blog settings to JSON file
 */
function save_settings($settings)
{
    $settings_file = CONTENT_DIR . 'settings.json';

    // Ensure content directory exists
    if (!file_exists(CONTENT_DIR)) {
        mkdir(CONTENT_DIR, 0755, true);
    }

    return file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
}

/**
 * Get all blog posts
 */
function get_posts($page = 1, $per_page = 10, $status = 'published')
{
    $posts = [];
    $posts_dir = CONTENT_DIR . 'posts/';

    if (!file_exists($posts_dir)) {
        return $posts;
    }

    // Clear stat cache to ensure fresh file listings
    clearstatcache(true, $posts_dir);
    $files = glob($posts_dir . '*.json');
    $all_posts = [];

    foreach ($files as $file) {
        // Clear stat cache for each file before reading
        clearstatcache(true, $file);
        $post_data = json_decode(file_get_contents($file), true);
        if ($post_data && ($status === 'all' || $post_data['status'] === $status)) {
            $all_posts[] = $post_data;
        }
    }

    // Sort by date (newest first)
    usort($all_posts, function ($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // Pagination
    $offset = ($page - 1) * $per_page;
    $posts = array_slice($all_posts, $offset, $per_page);

    return $posts;
}

/**
 * Get a single post by slug
 */
function get_post($slug)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return null;
    }

    // Clear stat cache before reading to ensure fresh data
    clearstatcache(true, $post_file);
    $post_data = json_decode(file_get_contents($post_file), true);
    return $post_data ?: null;
}

/**
 * Count total posts
 */
function count_posts($status = 'published')
{
    $posts_dir = CONTENT_DIR . 'posts/';

    if (!file_exists($posts_dir)) {
        return 0;
    }

    $files = glob($posts_dir . '*.json');
    $count = 0;

    foreach ($files as $file) {
        $post_data = json_decode(file_get_contents($file), true);
        if ($post_data && $post_data['status'] === $status) {
            $count++;
        }
    }

    return $count;
}

/**
 * Get pagination information
 */
function get_pagination_info($total_posts, $per_page, $current_page)
{
    $total_pages = ceil($total_posts / $per_page);

    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'prev_page' => $current_page > 1 ? $current_page - 1 : null,
        'next_page' => $current_page < $total_pages ? $current_page + 1 : null,
        'total_posts' => $total_posts,
        'per_page' => $per_page
    ];
}

/**
 * Create a new post
 */
function create_post($title, $content, $excerpt = '', $author = '', $status = 'draft', $tags = [])
{
    $slug = create_slug($title);
    $date = date('Y-m-d H:i:s');

    $post_data = [
        'title' => $title,
        'content' => $content,
        'excerpt' => $excerpt ?: substr(strip_tags($content), 0, 200) . '...',
        'author' => $author ?: (defined('ADMIN_USERNAME') ? constant('ADMIN_USERNAME') : 'Admin'),
        'date' => $date,
        'updated' => $date,
        'slug' => $slug,
        'status' => $status,
        'tags' => $tags,
        'views' => 0,
        'comments' => []
    ];

    $posts_dir = CONTENT_DIR . 'posts/';
    if (!file_exists($posts_dir)) {
        mkdir($posts_dir, 0755, true);
    }

    $post_file = $posts_dir . $slug . '.json';
    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Update an existing post
 */
function update_post($slug, $title, $content, $excerpt = '', $author = '', $status = 'draft', $tags = [])
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $post_data = json_decode(file_get_contents($post_file), true);
    if (!$post_data) {
        return false;
    }

    $post_data['title'] = $title;
    $post_data['content'] = $content;
    $post_data['excerpt'] = $excerpt ?: substr(strip_tags($content), 0, 200) . '...';
    $post_data['author'] = $author ?: $post_data['author'];
    $post_data['updated'] = date('Y-m-d H:i:s');
    $post_data['status'] = $status;
    $post_data['tags'] = $tags;

    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Delete a post
 */
function delete_post($slug)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $ok = unlink($post_file);
    if ($ok) {
        // Clear PHP's stat cache
        clearstatcache(true, $post_file);
        clearstatcache(true, dirname($post_file));
        // Rebuild index after deletion
        rebuild_index();
    }
    return $ok;
}

/**
 * Create URL-friendly slug from title
 */
function create_slug($title)
{
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');

    // Ensure uniqueness
    $original_slug = $slug;
    $counter = 1;

    while (file_exists(CONTENT_DIR . 'posts/' . $slug . '.json')) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }

    return $slug;
}


/**
 * Return all posts regardless of status (newest first)
 */
function all_posts()
{
    $posts_dir = CONTENT_DIR . 'posts/';
    $all = [];
    if (!file_exists($posts_dir)) {
        return $all;
    }
    // Clear stat cache to ensure fresh file listings
    clearstatcache(true, $posts_dir);
    $files = glob($posts_dir . '*.json');
    foreach ($files as $file) {
        // Clear stat cache for each file before reading
        clearstatcache(true, $file);
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $all[] = $data;
        }
    }
    usort($all, function ($a, $b) {
        return strtotime($b['date'] ?? '1970-01-01') - strtotime($a['date'] ?? '1970-01-01');
    });
    return $all;
}

/**
 * Get posts filtered by status (helper for admin/tools)
 */
function get_posts_by_status($status)
{
    $all = all_posts();
    return array_values(array_filter($all, function ($p) use ($status) {
        return isset($p['status']) && $p['status'] === $status;
    }));
}

/**
 * Rebuild the content index file from post JSON files
 */
function rebuild_index()
{
    $posts_dir = CONTENT_DIR . 'posts/';
    if (!file_exists($posts_dir)) {
        if (!mkdir($posts_dir, 0755, true)) {
            return false;
        }
    }

    $files = glob($posts_dir . '*.json');
    $index = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data) {
            continue;
        }
        $index[] = [
            'slug' => $data['slug'] ?? pathinfo($file, PATHINFO_FILENAME),
            'title' => $data['title'] ?? '',
            'date' => $data['date'] ?? '',
            'updated' => $data['updated'] ?? ($data['date'] ?? ''),
            'status' => $data['status'] ?? 'draft',
            'excerpt' => $data['excerpt'] ?? generate_excerpt($data['content'] ?? ($data['content_markdown'] ?? $data['content_html'] ?? '')),
            'tags' => $data['tags'] ?? [],
            'categories' => $data['categories'] ?? [],
            'author' => $data['author'] ?? (defined('ADMIN_USERNAME') ? constant('ADMIN_USERNAME') : 'Admin'),
            'meta' => $data['meta'] ?? []
        ];
    }
    // Sort newest first
    usort($index, function ($a, $b) {
        return strtotime($b['date'] ?? '1970-01-01') - strtotime($a['date'] ?? '1970-01-01');
    });
    return (bool) file_put_contents(CONTENT_DIR . 'index.json', json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Aliases for compatibility with admin code
 */
function load_post($slug)
{
    return get_post($slug);
}

function save_post(array $post_data)
{
    if (empty($post_data['slug'])) {
        return false;
    }
    $file = CONTENT_DIR . 'posts/' . $post_data['slug'] . '.json';
    if (!file_exists(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    $ok = (bool) file_put_contents($file, json_encode($post_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($ok) {
        // Clear PHP's stat cache to ensure fresh file reads
        clearstatcache(true, $file);
        // Clear directory stat cache
        clearstatcache(true, dirname($file));
        // Rebuild index
        rebuild_index();
    }
    return $ok;
}

function slugify($text)
{
    return create_slug($text);
}

/**
 * Increment post views
 */
function increment_post_views($slug)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $post_data = json_decode(file_get_contents($post_file), true);
    if (!$post_data) {
        return false;
    }

    $post_data['views'] = ($post_data['views'] ?? 0) + 1;

    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Add comment to post
 */
function add_comment($slug, $name, $email, $comment)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $post_data = json_decode(file_get_contents($post_file), true);
    if (!$post_data) {
        return false;
    }

    $comment_data = [
        'id' => uniqid(),
        'name' => $name,
        'email' => $email,
        'comment' => $comment,
        'date' => date('Y-m-d H:i:s'),
        'approved' => false
    ];

    $post_data['comments'][] = $comment_data;

    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Approve comment
 */
function approve_comment($slug, $comment_id)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $post_data = json_decode(file_get_contents($post_file), true);
    if (!$post_data) {
        return false;
    }

    foreach ($post_data['comments'] as &$comment) {
        if ($comment['id'] === $comment_id) {
            $comment['approved'] = true;
            break;
        }
    }

    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Delete comment
 */
function delete_comment($slug, $comment_id)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $post_data = json_decode(file_get_contents($post_file), true);
    if (!$post_data) {
        return false;
    }

    $post_data['comments'] = array_filter($post_data['comments'], function ($comment) use ($comment_id) {
        return $comment['id'] !== $comment_id;
    });

    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Check if user is logged in
 */
function is_logged_in()
{
    session_start();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Login user
 */
function login_user($username, $password)
{
    if (!defined('ADMIN_USERNAME') || !defined('ADMIN_PASSWORD_HASH')) {
        return false;
    }
    $admin_username = constant('ADMIN_USERNAME');
    $admin_password_hash = constant('ADMIN_PASSWORD_HASH');
    if ($username === $admin_username && password_verify($password, $admin_password_hash)) {
        session_start();
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        return true;
    }
    return false;
}

/**
 * Logout user
 */
function logout_user()
{
    session_start();
    session_destroy();
}

/**
 * Check session timeout
 */
function check_session_timeout()
{
    if (!is_logged_in()) {
        return false;
    }

    session_start();
    $timeout = defined('SESSION_TIMEOUT') ? constant('SESSION_TIMEOUT') : 3600; // Default 1 hour
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout) {
        logout_user();
        return false;
    }

    return true;
}

/**
 * Generate CSRF token
 */
function generate_csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input
 */
function sanitize_input($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validate_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Log error
 */
function log_error($message, $context = [])
{
    $logs_dir = defined('LOGS_DIR') ? constant('LOGS_DIR') : __DIR__ . '/logs/';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'ERROR',
        'message' => $message,
        'context' => $context,
        'file' => $_SERVER['PHP_SELF'] ?? 'unknown',
        'line' => debug_backtrace()[0]['line'] ?? 'unknown'
    ];

    $log_file = $logs_dir . 'error.log';
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Log info
 */
function log_info($message, $context = [])
{
    $logs_dir = defined('LOGS_DIR') ? constant('LOGS_DIR') : __DIR__ . '/logs/';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'INFO',
        'message' => $message,
        'context' => $context
    ];

    $log_file = $logs_dir . 'app.log';
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Get system health
 */
function get_system_health()
{
    $health = [
        'status' => 'healthy',
        'checks' => []
    ];

    // Check if content directory is writable
    $health['checks']['content_writable'] = is_writable(CONTENT_DIR);

    // Check if logs directory is writable
    $logs_dir = defined('LOGS_DIR') ? constant('LOGS_DIR') : __DIR__ . '/logs/';
    $health['checks']['logs_writable'] = is_writable($logs_dir);

    // Check if uploads directory is writable
    $uploads_dir = defined('UPLOADS_DIR') ? constant('UPLOADS_DIR') : __DIR__ . '/uploads/';
    $health['checks']['uploads_writable'] = is_writable($uploads_dir);

    // Check PHP version
    $health['checks']['php_version'] = version_compare(PHP_VERSION, '7.4.0', '>=');

    // Check required extensions
    $required_extensions = ['json', 'fileinfo', 'mbstring'];
    $health['checks']['extensions'] = [];
    foreach ($required_extensions as $ext) {
        $health['checks']['extensions'][$ext] = extension_loaded($ext);
    }

    // Overall status
    $all_checks = array_merge(
        [$health['checks']['content_writable'], $health['checks']['logs_writable'], $health['checks']['uploads_writable'], $health['checks']['php_version']],
        array_values($health['checks']['extensions'])
    );

    if (in_array(false, $all_checks)) {
        $health['status'] = 'warning';
    }

    return $health;
}

/**
 * Format date for display
 */
function format_date($date, $format = 'F j, Y')
{
    return date($format, strtotime($date));
}

/**
 * Generate excerpt from content
 */
function generate_excerpt($content, $length = 200)
{
    $excerpt = strip_tags($content);
    if (strlen($excerpt) > $length) {
        $excerpt = substr($excerpt, 0, $length) . '...';
    }
    return $excerpt;
}

/**
 * Get post URL
 */
function get_post_url($slug)
{
    return BASE_URL . 'post.php?slug=' . urlencode($slug);
}

/**
 * Get admin URL
 */
function get_admin_url($page = '')
{
    return BASE_URL . 'admin/' . $page;
}

/**
 * Redirect with message
 */
function redirect_with_message($url, $message, $type = 'info')
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit;
}

/**
 * Get and clear flash message
 */
function get_flash_message()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }

    return null;
}

/**
 * Check if maintenance mode is enabled
 */
function is_maintenance_mode()
{
    $settings = load_settings();
    return isset($settings['maintenance_mode']) && $settings['maintenance_mode'] === true;
}


/**
 * Clean old log files
 */
function clean_old_logs($days = 30)
{
    $logs_dir = defined('LOGS_DIR') ? constant('LOGS_DIR') : __DIR__ . '/logs/';
    if (!file_exists($logs_dir)) {
        return;
    }

    $files = glob($logs_dir . '*.log');
    $cutoff = time() - ($days * 24 * 60 * 60);

    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
}

/**
 * Backup content
 */
function backup_content()
{
    $logs_dir = defined('LOGS_DIR') ? constant('LOGS_DIR') : __DIR__ . '/logs/';
    $backup_dir = $logs_dir . 'backups/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $backup_file = $backup_dir . 'content_backup_' . date('Y-m-d_H-i-s') . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($backup_file, ZipArchive::CREATE) === TRUE) {
        $zip->addFile(CONTENT_DIR . 'settings.json', 'settings.json');
        $zip->addFile(CONTENT_DIR . 'index.json', 'index.json');

        // Add all posts
        $posts_dir = CONTENT_DIR . 'posts/';
        if (file_exists($posts_dir)) {
            $files = glob($posts_dir . '*.json');
            foreach ($files as $file) {
                $zip->addFile($file, 'posts/' . basename($file));
            }
        }

        $zip->close();
        return $backup_file;
    }

    return false;
}


/**
 * Initialize blog
 */
function init_blog()
{
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Set timezone
    if (defined('TIMEZONE')) {
        date_default_timezone_set(constant('TIMEZONE'));
    }

    // Check maintenance mode
    if (is_maintenance_mode() && !is_logged_in()) {
        http_response_code(503);
        // Maintenance mode - show simple message
        die('Site is currently under maintenance. Please check back later.');
    }

    // Clean old logs periodically
    if (rand(1, 100) === 1) {
        clean_old_logs();
    }
}

// Initialize blog
init_blog();
