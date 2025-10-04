<?php

/**
 * Settings Page
 * Simple, essential settings only
 */

require_once '../functions.php';
require_once __DIR__ . '/../libs/SecurityHardener.php';

// Initialize security system
SecurityHardener::init();

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $error_message = "Invalid CSRF token.";
        } else {
            // Update settings
            $settings = [
                'site_title' => trim($_POST['site_title'] ?? ''),
                'site_description' => trim($_POST['site_description'] ?? ''),
                'admin_email' => trim($_POST['admin_email'] ?? ''),
                'posts_per_page' => (int)($_POST['posts_per_page'] ?? 10),
                'updated' => date('c')
            ];

            // Save settings to file
            $settings_file = CONTENT_DIR . 'settings.json';
            if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                $success_message = "Settings updated successfully!";

                // Update config.php if site title changed
                if ($settings['site_title'] !== SITE_TITLE) {
                    update_config_site_title($settings['site_title']);
                }
            } else {
                $error_message = "Failed to save settings.";
            }
        }
    }
}

// Load current settings
$settings_file = CONTENT_DIR . 'settings.json';
$current_settings = [];
if (file_exists($settings_file)) {
    $current_settings = json_decode(file_get_contents($settings_file), true) ?: [];
}

// Default settings
$settings = array_merge([
    'site_title' => SITE_TITLE,
    'site_description' => 'A simple flat-file blog',
    'admin_email' => 'admin@example.com',
    'posts_per_page' => 10
], $current_settings);

// Function to update config.php
function update_config_site_title($new_title)
{
    $config_file = dirname(__DIR__) . '/config.php';
    $config_content = file_get_contents($config_file);
    $config_content = preg_replace(
        "/define\('SITE_TITLE', '[^']*'\);/",
        "define('SITE_TITLE', '" . addslashes($new_title) . "');",
        $config_content
    );
    file_put_contents($config_file, $config_content);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo SITE_TITLE; ?></title>
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
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/new-post">
                            <i class="bi bi-plus-circle"></i> New Post
                        </a>
                        <a class="nav-link text-light active" href="<?php echo BASE_URL; ?>admin/settings">
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
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h1 class="h3 mb-0">
                                    <i class="bi bi-gear"></i> Settings
                                </h1>
                            </div>

                            <!-- Messages -->
                            <?php if (isset($success_message)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <!-- Settings Form -->
                            <form method="POST" action="<?php echo BASE_URL; ?>admin/settings">
                                <input type="hidden" name="action" value="update_settings">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                <div class="row">
                                    <div class="col-lg-12">
                                        <!-- Blog Information -->
                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label for="site_title" class="form-label">Site Title *</label>
                                                    <input type="text" class="form-control" id="site_title" name="site_title"
                                                        value="<?php echo htmlspecialchars($settings['site_title']); ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="site_description" class="form-label">Site Description</label>
                                                    <textarea class="form-control" id="site_description" name="site_description" rows="3"
                                                        placeholder="Brief description of your blog"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="admin_email" class="form-label">Admin Email</label>
                                                    <input type="email" class="form-control" id="admin_email" name="admin_email"
                                                        value="<?php echo htmlspecialchars($settings['admin_email']); ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label for="posts_per_page" class="form-label">Posts Per Page</label>
                                                    <input type="number" class="form-control" id="posts_per_page" name="posts_per_page"
                                                        value="<?php echo $settings['posts_per_page']; ?>" min="1" max="50">
                                                </div>

                                                <div class="d-grid">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bi bi-check-circle"></i> Save Settings
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>