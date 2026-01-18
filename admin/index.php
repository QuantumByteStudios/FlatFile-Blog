<?php

/**
 * Admin Dashboard
 * Professional admin panel for FlatFile Blog
 */

// Check if blog is installed
$config_path = file_exists('../config.php') ? '../config.php' : 'config.php';
if (!file_exists($config_path)) {
    header('Location: ../install.php');
    exit;
}

// Define constant to allow access
define('ALLOW_DIRECT_ACCESS', true);

try {
    $functions_path = file_exists('../functions.php') ? '../functions.php' : 'functions.php';
    require_once $functions_path;
} catch (Exception $e) {
    error_log('Error loading functions.php: ' . $e->getMessage());
    die('System error. Please try again later.');
}
require_once __DIR__ . '/../libs/SecurityHardener.php';

// Initialize security system
SecurityHardener::init();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set cache control headers to prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Handle success/error messages from URL parameters
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    $success_message = htmlspecialchars(urldecode($_GET['success']), ENT_QUOTES, 'UTF-8');
}
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars(urldecode($_GET['error']), ENT_QUOTES, 'UTF-8');
}

// Clear stat cache to ensure fresh data
clearstatcache(true);

// Load dashboard data (optimized - only get what we need)
$all_posts = get_posts(1, 1000, 'all'); // Get all posts (published and drafts)
$published_posts = array_filter($all_posts, function ($post) {
    return ($post['status'] ?? '') === 'published';
});
$draft_posts = array_filter($all_posts, function ($post) {
    return ($post['status'] ?? '') === 'draft';
});
$total_posts = count($all_posts);
$published_count = count($published_posts);
$draft_count = count($draft_posts);

// Get recent posts (last 5)
$recent_posts = array_slice($all_posts, 0, 5);

// Get posts by month for chart data
$posts_by_month = [];
foreach ($all_posts as $post) {
    if (!empty($post['date'])) {
        $timestamp = strtotime($post['date']);
        if ($timestamp !== false) {
            $month = date('Y-m', $timestamp);
            $posts_by_month[$month] = ($posts_by_month[$month] ?? 0) + 1;
        }
    }
}

// Get most used tags
$all_tags = [];
foreach ($all_posts as $post) {
    if (!empty($post['tags'])) {
        foreach ($post['tags'] as $tag) {
            $all_tags[$tag] = ($all_tags[$tag] ?? 0) + 1;
        }
    }
}
arsort($all_tags);
$top_tags = array_slice($all_tags, 0, 5, true);

// Load settings
$settings = load_settings();
$install_warning = file_exists(__DIR__ . '/../install.php');
$csrf_token = function_exists('generate_csrf_token') ? generate_csrf_token() : ($_SESSION['csrf_token'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Admin Dashboard - <?php echo SITE_TITLE; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/main.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 bg-dark min-vh-100 p-0">
                <div class="p-3">
                    <center>
                        <h5 class="text-light mb-4">
                            <a href="." class="text-light text-decoration-none">
                                Admin Panel
                            </a>
                        </h5>
                    </center>
                    <nav class="nav flex-column">
                        <a class="nav-link text-light active" href="<?php echo BASE_URL; ?>admin/">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/new-post">
                            <i class="bi bi-plus-circle"></i> New Post
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/settings">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/admin-tools">
                            <i class="bi bi-tools"></i> Tools
                        </a>
                        <hr class="text-light">
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>" target="_blank">
                            <i class="bi bi-eye"></i> View Site
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/logout">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <!-- Success/Error Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Dashboard</h1>
                        <div class="text-muted">
                            Welcome back, Admin
                        </div>
                    </div>

                    <?php if ($install_warning): ?>
                        <div class="alert alert-danger d-flex justify-content-between align-items-center" role="alert">
                            <div>
                                <i class="bi bi-shield-exclamation me-2"></i>
                                For security, please delete the <code>install.php</code> file from the root directory.
                            </div>
                            <form method="POST" action="<?php echo BASE_URL; ?>admin_action" class="ms-3">
                                <input type="hidden" name="action" value="delete_install_file">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" class="btn btn-sm btn-danger"
                                    onclick="return confirm('Delete install.php now?');">
                                    <i class="bi bi-trash"></i> Delete install.php
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-1 fw-bold"><?php echo $total_posts; ?></h3>
                                            <p class="mb-0 opacity-75">Total Posts</p>
                                        </div>
                                        <div class="opacity-75">
                                            <i class="bi bi-file-text" style="font-size: 2.5rem;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card stats-card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-1 fw-bold"><?php echo $published_count; ?></h3>
                                            <p class="mb-0 opacity-75">Published</p>
                                        </div>
                                        <div class="opacity-75">
                                            <i class="bi bi-check-circle" style="font-size: 2.5rem;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card stats-card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-1 fw-bold"><?php echo $draft_count; ?></h3>
                                            <p class="mb-0 opacity-75">Drafts</p>
                                        </div>
                                        <div class="opacity-75">
                                            <i class="bi bi-pencil" style="font-size: 2.5rem;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card stats-card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-1 fw-bold"><?php echo count($top_tags); ?></h3>
                                            <p class="mb-0 opacity-75">Active Tags</p>
                                        </div>
                                        <div class="opacity-75">
                                            <i class="bi bi-tags" style="font-size: 2.5rem;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Dashboard Content -->
                    <div class="row">
                        <!-- Recent Posts -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Recent Posts</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($recent_posts)): ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($recent_posts as $post): ?>
                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div class="flex-grow-1 overflow-hidden" style="min-width:0;">
                                                        <h6 class="mb-1 text-truncate">
                                                            <a href="<?php echo BASE_URL; ?><?php echo urlencode($post['slug']); ?>"
                                                                class="text-decoration-none d-inline-block text-truncate w-100"
                                                                target="_blank">
                                                                <?php echo htmlspecialchars($post['title']); ?>
                                                            </a>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?php echo date('M j, Y', strtotime($post['date'])); ?> •
                                                            By <?php echo htmlspecialchars($post['author']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="ms-3 text-nowrap">
                                                        <span
                                                            class="badge bg-<?php echo $post['status'] === 'published' ? 'success' : 'warning'; ?> me-2">
                                                            <?php echo ucfirst($post['status']); ?>
                                                        </span>
                                                        <div class="btn-group" role="group">
                                                            <a href="<?php echo BASE_URL; ?><?php echo urlencode($post['slug']); ?>"
                                                                class="btn btn-sm btn-outline-dark" target="_blank"
                                                                title="View">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="edit-post?slug=<?php echo htmlspecialchars(urlencode($post['slug']), ENT_QUOTES, 'UTF-8'); ?>"
                                                                class="btn btn-sm btn-outline-dark" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="delete-post?slug=<?php echo htmlspecialchars(urlencode($post['slug']), ENT_QUOTES, 'UTF-8'); ?>"
                                                                class="btn btn-sm btn-outline-dark"
                                                                onclick="return confirm('Are you sure you want to delete this post?')"
                                                                title="Delete">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="bi bi-file-text text-muted" style="font-size: 3rem;"></i>
                                            <p class="text-muted mt-2">No posts yet</p>
                                            <a href="new-post" class="btn btn-primary">Create your first post</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <div class="col-lg-4">
                            <!-- Recent Activity -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Recent Activity</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($recent_posts)): ?>
                                        <?php foreach (array_slice($recent_posts, 0, 3) as $post): ?>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="flex-shrink-0">
                                                    <i
                                                        class="bi bi-<?php echo $post['status'] === 'published' ? 'check-circle text-success' : 'pencil text-warning'; ?>"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-2 overflow-hidden" style="min-width:0;">
                                                    <div class="fw-bold small text-truncate">
                                                        <?php echo htmlspecialchars($post['title']); ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?php echo date('M j, Y', strtotime($post['date'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted mb-0 small">No recent activity</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Top Tags -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Popular Tags</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($top_tags)): ?>
                                        <?php foreach ($top_tags as $tag => $count): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span
                                                    class="badge bg-light text-dark"><?php echo htmlspecialchars($tag); ?></span>
                                                <small class="text-muted"><?php echo $count; ?> posts</small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">No tags yet</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- System Info -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">System Info</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="border-end">
                                                <h6 class="mb-1"><?php echo $published_count; ?></h6>
                                                <small class="text-muted">Published</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <h6 class="mb-1"><?php echo $draft_count; ?></h6>
                                            <small class="text-muted">Drafts</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>