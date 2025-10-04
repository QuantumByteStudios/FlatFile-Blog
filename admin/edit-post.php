<?php

/**
 * Edit Post
 */

// Check if blog is installed
if (!file_exists('../config.php')) {
    header('Location: ../install.php');
    exit;
}

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

$success_message = '';
$error_message = '';

// Get post slug
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: index');
    exit;
}

// Load post
$post = get_post($slug);
if (!$post) {
    header('Location: index');
    exit;
}

// Determine effective content type for editing
$effective_content_type = $post['content_type'] ?? (isset($post['content_markdown']) ? 'markdown' : 'html');

// Handle success/error messages from URL parameters
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error_message = $_GET['error'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post - <?php echo SITE_TITLE; ?></title>
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
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Edit Post</h1>
                        <div>
                            <a href="<?php echo BASE_URL; ?><?php echo $post['slug']; ?>" target="_blank" class="btn btn-outline-primary me-2">
                                <i class="bi bi-eye"></i> View Post
                            </a>
                        </div>
                    </div>

                    <!-- Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Post Form -->
                    <form method="POST" action="<?php echo BASE_URL; ?>admin_action" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="original_slug" value="<?php echo htmlspecialchars($post['slug']); ?>">
                        <input type="hidden" name="slug" value="<?php echo htmlspecialchars($post['slug']); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Main Content -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Post Content</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="title" class="form-label">Title *</label>
                                            <input type="text" class="form-control" id="title" name="title"
                                                value="<?php echo htmlspecialchars($post['title']); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="slug" class="form-label">Slug</label>
                                            <input type="text" class="form-control" id="slug" name="slug"
                                                value="<?php echo htmlspecialchars($post['slug']); ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label for="excerpt" class="form-label">Excerpt</label>
                                            <textarea class="form-control" id="excerpt" name="excerpt" rows="3"
                                                placeholder="Brief description of the post"><?php echo htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="content_type" class="form-label">Content Type</label>
                                            <select class="form-select" id="content_type" name="content_type" onchange="toggleContentType()">
                                                <option value="markdown" <?php echo ($effective_content_type === 'markdown') ? 'selected' : ''; ?>>Markdown</option>
                                                <option value="html" <?php echo ($effective_content_type === 'html') ? 'selected' : ''; ?>>HTML</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="content" class="form-label">Content *</label>
                                            <textarea class="form-control" id="content" name="content" rows="15" required><?php echo htmlspecialchars(($effective_content_type === 'markdown') ? ($post['content_markdown'] ?? $post['content'] ?? '') : ($post['content_html'] ?? $post['content'] ?? '')); ?></textarea>
                                            <div class="form-text" id="content-help">
                                                <strong>Markdown supported:</strong> Use **bold**, *italic*, `code`, [links](url), # headers, etc.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <!-- Publish Settings -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Publish Settings</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="draft" <?php echo $post['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                <option value="published" <?php echo $post['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="author" class="form-label">Author</label>
                                            <input type="text" class="form-control" id="author" name="author"
                                                value="<?php echo htmlspecialchars($post['author'] ?? 'Admin'); ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label for="featured_image" class="form-label">Featured Image</label>
                                            <input type="file" class="form-control" id="featured_image" name="featured_image" accept="image/*">
                                            <div class="form-text">Upload a new featured image (JPG, PNG, GIF, WebP)</div>
                                            <?php if (!empty($post['meta']['image'])): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">Current image: <a href="<?php echo htmlspecialchars($post['meta']['image']); ?>" target="_blank">View</a></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Created</label>
                                            <p class="form-control-plaintext"><?php echo date('M j, Y g:i A', strtotime($post['date'])); ?></p>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Last Updated</label>
                                            <p class="form-control-plaintext"><?php echo date('M j, Y g:i A', strtotime($post['updated'])); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tags & Categories -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Tags & Categories</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="tags" class="form-label">Tags</label>
                                            <input type="text" class="form-control" id="tags" name="tags"
                                                value="<?php echo htmlspecialchars(implode(', ', $post['tags'] ?? [])); ?>"
                                                placeholder="tag1, tag2, tag3">
                                        </div>

                                        <div class="mb-3">
                                            <label for="categories" class="form-label">Categories</label>
                                            <input type="text" class="form-control" id="categories" name="categories"
                                                value="<?php echo htmlspecialchars(implode(', ', $post['categories'] ?? [])); ?>"
                                                placeholder="category1, category2">
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Update Post
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>admin" class="btn btn-outline-secondary">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-generate slug from title
        document.getElementById('title').addEventListener('input', function() {
            const title = this.value;
            const slug = title.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            document.getElementById('slug').value = slug;
        });

        // Toggle content type help text
        function toggleContentType() {
            const contentType = document.getElementById('content_type').value;
            const contentHelp = document.getElementById('content-help');
            const contentTextarea = document.getElementById('content');

            if (contentType === 'html') {
                contentHelp.innerHTML = '<strong>HTML supported:</strong> Use HTML tags like &lt;h1&gt;, &lt;p&gt;, &lt;img&gt;, &lt;a&gt;, etc.';
                contentTextarea.placeholder = 'Write your post content in HTML...';
            } else {
                contentHelp.innerHTML = '<strong>Markdown supported:</strong> Use **bold**, *italic*, `code`, [links](url), # headers, etc.';
                contentTextarea.placeholder = 'Write your post content in Markdown...';
            }
        }
    </script>
</body>

</html>