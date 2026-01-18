<?php

/**
 * FlatFile Blog - Admin Actions
 * Handles POST requests for create, update, delete operations
 */

require_once 'functions.php';
require_once __DIR__ . '/libs/SecurityHardener.php';
// Removed unused MonitoringSystem and GoogleAIClient
require_once __DIR__ . '/libs/OpenAIClient.php';

// Initialize security system
SecurityHardener::init();
require_once 'libs/ImageUploader.php';
require_once 'libs/AdminLogger.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied. Please log in.');
}

// Security checks
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$username = $_SESSION['username'] ?? 'unknown';

// Check brute force protection
$brute_force_check = SecurityHardener::checkBruteForce($ip, $username);
if ($brute_force_check['blocked']) {
    http_response_code(429);
    die(json_encode(['error' => $brute_force_check['message']]));
}

// Check rate limiting
$rate_limit_check = SecurityHardener::checkRateLimit($ip);
if ($rate_limit_check['blocked']) {
    http_response_code(429);
    die(json_encode(['error' => $rate_limit_check['message']]));
}

// Handle GET requests for AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'get_post' && isset($_GET['slug'])) {
        $slug = $_GET['slug'];
        $post = load_post($slug);

        if ($post) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'post' => $post]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Post not found']);
        }
        exit;
    }

    // Generate AI post content
    if ($action === 'generate_ai_post') {
        header('Content-Type: application/json');
        // Avoid leaking PHP warnings/notices into JSON
        if (function_exists('ob_get_level') && ob_get_level() > 0) {
            ob_clean();
        }
        ini_set('display_errors', '0');

        if (!extension_loaded('curl')) {
            echo json_encode(['success' => false, 'error' => 'Server missing PHP cURL extension. Enable it in php.ini and restart Apache.']);
            exit;
        }
        $topic = trim($_GET['topic'] ?? '');
        if ($topic === '') {
            echo json_encode(['success' => false, 'error' => 'Topic is required']);
            exit;
        }

        $settings = load_settings();
        $businessInfo = trim($settings['business_info'] ?? '');

        try {
            $openaiKey = trim($settings['openai_api_key'] ?? '');
            $openaiModel = trim($settings['openai_model'] ?? 'openai/gpt-4o-mini');
            $openaiEndpoint = trim($settings['openai_endpoint'] ?? 'https://models.github.ai/inference');
            if ($openaiKey === '') {
                echo json_encode(['success' => false, 'error' => 'OpenAI token not configured. Add it in Settings.']);
                exit;
            }
            $client = new OpenAIClient($openaiKey, $openaiModel, $openaiEndpoint);
            $result = $client->generateBlogContent($topic, $businessInfo);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
            exit;
        }
        if (!($result['success'] ?? false)) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'AI generation failed']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'title' => $result['title'] ?? '',
            'excerpt' => $result['excerpt'] ?? '',
            'tags' => $result['tags'] ?? [],
            'categories' => $result['categories'] ?? [],
            'slug' => isset($result['title']) ? slugify($result['title']) : '',
            'content_html' => $result['content_html'] ?? ''
        ]);
        exit;
    }
}

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('HTTP/1.0 403 Forbidden');
        die('Invalid security token.');
    }
}

$action = $_POST['action'] ?? '';
$success_message = '';
$error_message = '';

switch ($action) {
    case 'create':
        $result = handle_create_post();
        if ($result['success']) {
            $success_message = 'Post created successfully!';
            AdminLogger::log('post_created', [
                'slug' => $result['slug'] ?? 'unknown',
                'title' => $_POST['title'] ?? 'unknown'
            ]);
        } else {
            $error_message = $result['error'];
            AdminLogger::log('post_create_failed', [
                'error' => $result['error'],
                'title' => $_POST['title'] ?? 'unknown'
            ]);
        }
        break;

    case 'update':
        $result = handle_update_post();
        if ($result['success']) {
            $success_message = 'Post updated successfully!';
            AdminLogger::log('post_updated', [
                'slug' => $_POST['slug'] ?? 'unknown',
                'title' => $_POST['title'] ?? 'unknown'
            ]);
        } else {
            $error_message = $result['error'];
            AdminLogger::log('post_update_failed', [
                'error' => $result['error'],
                'slug' => $_POST['slug'] ?? 'unknown'
            ]);
        }
        break;

    case 'delete':
        $result = handle_delete_post();
        if ($result['success']) {
            $success_message = 'Post deleted successfully!';
            AdminLogger::log('post_deleted', [
                'slug' => $_POST['slug'] ?? 'unknown'
            ]);
        } else {
            $error_message = $result['error'];
            AdminLogger::log('post_delete_failed', [
                'error' => $result['error'],
                'slug' => $_POST['slug'] ?? 'unknown'
            ]);
        }
        break;

    case 'delete_install_file':
        $install_path = __DIR__ . '/install.php';
        if (!file_exists($install_path)) {
            $error_message = 'install.php was not found.';
            AdminLogger::log('install_delete_missing', []);
            break;
        }
        if (!is_writable($install_path)) {
            $error_message = 'install.php is not writable. Delete manually.';
            AdminLogger::log('install_delete_not_writable', []);
            break;
        }
        if (@unlink($install_path)) {
            $success_message = 'install.php deleted successfully.';
            AdminLogger::log('install_deleted', []);
        } else {
            $error_message = 'Failed to delete install.php. Check permissions.';
            AdminLogger::log('install_delete_failed', []);
        }
        break;

    default:
        $error_message = 'Invalid action.';
        AdminLogger::log('invalid_action', [
            'action' => $action
        ]);
}

// Set cache control headers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Redirect back to appropriate page with message
$redirect_url = BASE_URL . 'admin/';

// Handle different redirects based on action
if ($action === 'update') {
    $slug = $_POST['slug'] ?? '';
    // Add cache-busting parameter to force reload
    $cache_buster = '&_t=' . time();
    $redirect_url = BASE_URL . 'admin/edit-post?slug=' . urlencode($slug) . $cache_buster;
} elseif ($action === 'create') {
    // Add cache-busting parameter
    $redirect_url = BASE_URL . 'admin/?_t=' . time();
}

if ($success_message) {
    $redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . 'success=' . urlencode($success_message);
} elseif ($error_message) {
    $redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . 'error=' . urlencode($error_message);
}

header('Location: ' . $redirect_url);
exit;

/**
 * Handle create post action
 */
function handle_create_post()
{
    // Validate required fields
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title)) {
        return ['success' => false, 'error' => 'Title is required.'];
    }

    if (empty($content)) {
        return ['success' => false, 'error' => 'Content is required.'];
    }

    // Sanitize and validate data
    $slug = trim($_POST['slug'] ?? '');
    if (empty($slug)) {
        $slug = slugify($title);
    } else {
        $slug = slugify($slug);
    }

    // Check if slug already exists
    if (load_post($slug)) {
        $slug = $slug . '-' . time();
    }

    $excerpt = trim($_POST['excerpt'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'published';
    // Date handling: accept datetime-local (YYYY-MM-DDTHH:MM) or ISO and convert to ISO8601
    $date_input = trim($_POST['date'] ?? '');
    $date_ts = $date_input !== '' ? strtotime($date_input) : time();
    $date = date('c', $date_ts);
    // Updated handling: allow override; default to publish date/time
    $updated_input = trim($_POST['updated'] ?? '');
    $updated_ts = $updated_input !== '' ? strtotime($updated_input) : $date_ts;
    $updated = date('c', $updated_ts);
    $author = trim($_POST['author'] ?? 'Admin');

    // Parse tags and categories
    $tags = [];
    if (!empty($_POST['tags'])) {
        $tags = array_map('trim', explode(',', $_POST['tags']));
        $tags = array_filter($tags);
    }

    $categories = [];
    if (!empty($_POST['categories'])) {
        $categories = array_map('trim', explode(',', $_POST['categories']));
        $categories = array_filter($categories);
    }

    // Handle featured image upload
    $featured_image = '';
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = ImageUploader::upload($_FILES['featured_image'], 'featured');
        if ($upload_result['success']) {
            $featured_image = $upload_result['url'];
        }
    }

    // Get content type (align with post.php/edit-post logic)
    $content_type = $_POST['content_type'] ?? 'html';

    // Create post data
    $post_data = [
        'slug' => $slug,
        'title' => $title,
        'content_type' => $content_type,
        'excerpt' => $excerpt,
        'status' => $status,
        'date' => $date,
        'updated' => $updated,
        'author' => $author,
        'tags' => $tags,
        'categories' => $categories,
        'meta' => [
            'image' => $featured_image
        ]
    ];

    // Store content based on type
    if ($content_type === 'html') {
        $post_data['content_html'] = $content;
        unset($post_data['content_markdown']);
    } else {
        $post_data['content_markdown'] = $content;
        unset($post_data['content_html']);
    }

    // Save post
    if (save_post($post_data)) {
        // Force clear any opcache if enabled
        if (function_exists('opcache_invalidate')) {
            $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';
            opcache_invalidate($post_file, true);
        }
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Failed to save post. Check file permissions.'];
    }
}

/**
 * Handle update post action
 */
function handle_update_post()
{
    // Identify the post to update by its original slug (stable identifier)
    $original_slug = trim($_POST['original_slug'] ?? '');
    $slug = trim($_POST['slug'] ?? '');

    if (empty($original_slug)) {
        return ['success' => false, 'error' => 'Original post slug is required.'];
    }

    // Load existing post
    $existing_post = load_post($original_slug);
    if (!$existing_post) {
        return ['success' => false, 'error' => 'Post not found.'];
    }

    // Validate required fields
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title)) {
        return ['success' => false, 'error' => 'Title is required.'];
    }

    if (empty($content)) {
        return ['success' => false, 'error' => 'Content is required.'];
    }

    // Get content type
    $content_type = $_POST['content_type'] ?? ($existing_post['content_type'] ?? 'markdown');

    // Update post data (allow title change and optional slug change)
    $existing_post['title'] = $title;
    // If slug field was changed, ensure uniqueness and rename the file
    if (!empty($slug) && $slug !== $original_slug) {
        $new_slug = slugify($slug);
        if ($new_slug !== $original_slug) {
            // If target slug exists, make it unique
            if (load_post($new_slug)) {
                $new_slug = $new_slug . '-' . time();
            }
            // Rename file on disk if present
            $old_path = CONTENT_DIR . 'posts/' . $original_slug . '.json';
            $new_path = CONTENT_DIR . 'posts/' . $new_slug . '.json';
            if (file_exists($old_path)) {
                @rename($old_path, $new_path);
                // Reload the existing post data from new path if rename succeeded
                if (file_exists($new_path)) {
                    $existing_post['slug'] = $new_slug;
                }
            } else {
                $existing_post['slug'] = $new_slug;
            }
        }
    }
    $existing_post['content_type'] = $content_type;
    $existing_post['excerpt'] = trim($_POST['excerpt'] ?? '');
    $existing_post['status'] = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : $existing_post['status'];
    $existing_post['author'] = trim($_POST['author'] ?? $existing_post['author']);
    // Optional date override
    if (isset($_POST['date']) && trim($_POST['date']) !== '') {
        $date_ts = strtotime(trim($_POST['date']));
        if ($date_ts !== false) {
            $existing_post['date'] = date('c', $date_ts);
        }
    }
    // Updated time: allow override, else default to now
    if (isset($_POST['updated']) && trim($_POST['updated']) !== '') {
        $updated_ts = strtotime(trim($_POST['updated']));
        $existing_post['updated'] = $updated_ts !== false ? date('c', $updated_ts) : date('c');
    } else {
        $existing_post['updated'] = date('c');
    }

    // Store content based on type
    if ($content_type === 'html') {
        $existing_post['content_html'] = $content;
        unset($existing_post['content_markdown']);
    } else {
        $existing_post['content_markdown'] = $content;
        unset($existing_post['content_html']);
    }

    // Parse tags and categories
    if (!empty($_POST['tags'])) {
        $existing_post['tags'] = array_filter(array_map('trim', explode(',', $_POST['tags'])));
    }

    if (!empty($_POST['categories'])) {
        $existing_post['categories'] = array_filter(array_map('trim', explode(',', $_POST['categories'])));
    }

    // Handle featured image upload
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = ImageUploader::upload($_FILES['featured_image'], 'featured');
        if ($upload_result['success']) {
            $existing_post['meta']['image'] = $upload_result['url'];
        }
    }

    // Save updated post
    if (save_post($existing_post)) {
        // Force clear any opcache if enabled
        if (function_exists('opcache_invalidate')) {
            $post_file = CONTENT_DIR . 'posts/' . $existing_post['slug'] . '.json';
            opcache_invalidate($post_file, true);
        }
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Failed to update post. Check file permissions.'];
    }
}

/**
 * Handle delete post action
 */
function handle_delete_post()
{
    $slug = trim($_POST['slug'] ?? '');

    if (empty($slug)) {
        return ['success' => false, 'error' => 'Post slug is required.'];
    }

    // Check if post exists
    if (!load_post($slug)) {
        return ['success' => false, 'error' => 'Post not found.'];
    }

    // Delete post
    if (delete_post($slug)) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Failed to delete post. Check file permissions.'];
    }
}
