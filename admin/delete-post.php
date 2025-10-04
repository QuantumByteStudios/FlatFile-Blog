<?php

/**
 * Delete Post
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

// Get post slug
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: index');
    exit;
}

// Load post to get title for confirmation
$post = get_post($slug);
if (!$post) {
    header('Location: index');
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if (delete_post($slug)) {
        header('Location: index?success=Post+deleted+successfully!');
        exit;
    } else {
        $error_message = "Failed to delete post.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Post - <?php echo SITE_TITLE; ?></title>
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
                    <h5 class="text-light mb-4">
                        Admin Panel
                    </h5>
                    <nav class="nav flex-column">
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/new-post">
                            <i class="bi bi-plus-circle"></i> New Post
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/settings">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/monitoring">
                            <i class="bi bi-graph-up"></i> Monitoring
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/tools">
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
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Delete Post</h1>
                        <a href="<?php echo BASE_URL; ?>admin" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>

                    <!-- Error Message -->
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Confirmation -->
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0">
                                        <i class="bi bi-exclamation-triangle"></i> Confirm Deletion
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-warning">
                                        <strong>Warning:</strong> This action cannot be undone!
                                    </div>

                                    <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                                    <p class="text-muted">
                                        <strong>Slug:</strong> <?php echo htmlspecialchars($post['slug']); ?><br>
                                        <strong>Status:</strong> <?php echo ucfirst($post['status']); ?><br>
                                        <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($post['date'])); ?>
                                    </p>

                                    <?php if (!empty($post['excerpt'])): ?>
                                        <p><strong>Excerpt:</strong></p>
                                        <p class="text-muted"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                                    <?php endif; ?>

                                    <form method="POST" class="mt-4">
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                                <i class="bi bi-trash"></i> Yes, Delete Post
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>admin" class="btn btn-outline-secondary">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                        </div>
                                    </form>
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