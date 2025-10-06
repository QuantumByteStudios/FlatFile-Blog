<?php

/**
 * Create New Post
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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login');
    exit;
}

$success_message = '';
$error_message = '';

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
    <title>New Post - <?php echo SITE_TITLE; ?></title>
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
                        <a class="nav-link text-light active" href="<?php echo BASE_URL; ?>admin/new-post">
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
                        <h1 class="h3 mb-0">New Post</h1>
                        <a href="<?php echo BASE_URL; ?>admin" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
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
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Main Content -->
                                <div class="card mb-4">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Post Content</h5>
                                        <button style="background: linear-gradient(50deg, #c77dff 0%, #e0aaff 100%); color: white; border: 1px solid #c77dff" type="button" class="btn" data-bs-toggle="modal" data-bs-target="#aiModal">
                                            <i class="bi bi-stars"></i> Generate with AI
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="title" class="form-label">Title *</label>
                                            <input type="text" class="form-control" id="title" name="title"
                                                value="<?php echo htmlspecialchars($title ?? ''); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="slug" class="form-label">Slug</label>
                                            <input type="text" class="form-control" id="slug" name="slug"
                                                value="<?php echo htmlspecialchars($slug ?? ''); ?>"
                                                placeholder="auto-generated from title">
                                        </div>

                                        <div class="mb-3">
                                            <label for="excerpt" class="form-label">Excerpt</label>
                                            <textarea class="form-control" id="excerpt" name="excerpt" rows="3"
                                                placeholder="Brief description of the post"><?php echo htmlspecialchars($excerpt ?? ''); ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="content_type" class="form-label">Content Type</label>
                                            <select class="form-select" id="content_type" name="content_type" onchange="toggleContentType()">
                                                <option value="html" <?php echo ($content_type ?? 'html') === 'html' ? 'selected' : ''; ?>>HTML</option>
                                                <option value="markdown" <?php echo ($content_type ?? 'html') === 'markdown' ? 'selected' : ''; ?>>Markdown</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="content" class="form-label">Content *</label>
                                            <textarea class="form-control" id="content" name="content" rows="15" required
                                                placeholder="Write your post content..."><?php echo htmlspecialchars($content ?? ''); ?></textarea>
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
                                                <option value="draft" <?php echo ($status ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                <option value="published" <?php echo ($status ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="author" class="form-label">Author</label>
                                            <input type="text" class="form-control" id="author" name="author"
                                                value="<?php echo htmlspecialchars($author ?? 'Admin'); ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label for="featured_image" class="form-label">Featured Image</label>
                                            <input type="file" class="form-control" id="featured_image" name="featured_image" accept="image/*">
                                            <div class="form-text">Upload a featured image for this post (JPG, PNG, GIF, WebP)</div>
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
                                                value="<?php echo htmlspecialchars(implode(', ', $tags ?? [])); ?>"
                                                placeholder="tag1, tag2, tag3">
                                        </div>

                                        <div class="mb-3">
                                            <label for="categories" class="form-label">Categories</label>
                                            <input type="text" class="form-control" id="categories" name="categories"
                                                value="<?php echo htmlspecialchars(implode(', ', $categories ?? [])); ?>"
                                                placeholder="category1, category2">
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-dark text-start">
                                                <i class="bi bi-check-circle"></i> Create Post
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>admin" class="btn btn-dark text-start">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- AI Modal -->
                    <div class="modal fade" id="aiModal" tabindex="-1" aria-labelledby="aiModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="aiModalLabel"><i class="bi bi-stars"></i> Generate Blog with AI</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="ai_topic" class="form-label">Topic</label>
                                        <input type="text" class="form-control" id="ai_topic" placeholder="e.g. Local SEO tips for dentists">
                                        <div class="form-text">Describe the topic you want a blog about.</div>
                                    </div>
                                    <div id="ai_error" class="alert alert-danger d-none" role="alert"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" id="ai_generate_btn" class="btn btn-primary">
                                        <span class="spinner-border spinner-border-sm d-none" id="ai_spinner" role="status" aria-hidden="true"></span>
                                        <span id="ai_generate_text">Generate</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
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
                contentHelp.innerHTML = '<strong>HTML supported:</strong> Use only <h1>, <h2>, <h3>, <p>.';
                contentTextarea.placeholder = 'Write your post content in HTML...';
            } else {
                contentHelp.innerHTML = '<strong>Markdown supported:</strong> Use **bold**, *italic*, `code`, [links](url), # headers, etc.';
                contentTextarea.placeholder = 'Write your post content in Markdown...';
            }
        }

        // AI Generate flow
        (function() {
            const btn = document.getElementById('ai_generate_btn');
            const spinner = document.getElementById('ai_spinner');
            const btnText = document.getElementById('ai_generate_text');
            const errorBox = document.getElementById('ai_error');
            const aiTopic = document.getElementById('ai_topic');

            function setLoading(isLoading) {
                if (isLoading) {
                    spinner.classList.remove('d-none');
                    btnText.textContent = 'Generating…';
                    btn.disabled = true;
                } else {
                    spinner.classList.add('d-none');
                    btnText.textContent = 'Generate';
                    btn.disabled = false;
                }
            }

            btn.addEventListener('click', async function() {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
                const topic = (aiTopic.value || '').trim();
                if (!topic) {
                    errorBox.textContent = 'Please enter a topic.';
                    errorBox.classList.remove('d-none');
                    return;
                }
                setLoading(true);
                try {
                    const resp = await fetch('<?php echo BASE_URL; ?>admin_action?action=generate_ai_post&topic=' + encodeURIComponent(topic), {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    });
                    const data = await resp.json();
                    if (!data.success) {
                        throw new Error(data.error || 'Generation failed');
                    }
                    // Populate fields
                    document.getElementById('title').value = data.title || '';
                    document.getElementById('excerpt').value = data.excerpt || '';
                    const tagsField = document.getElementById('tags');
                    if (Array.isArray(data.tags)) {
                        tagsField.value = data.tags.join(', ');
                    }
                    const catsField = document.getElementById('categories');
                    if (Array.isArray(data.categories)) {
                        catsField.value = data.categories.join(', ');
                    }
                    if (data.slug) {
                        document.getElementById('slug').value = data.slug;
                    }
                    document.getElementById('content_type').value = 'html';
                    toggleContentType();
                    document.getElementById('content').value = data.content_html || '';

                    // Close modal
                    const modalEl = document.getElementById('aiModal');
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                } catch (e) {
                    errorBox.textContent = e.message || 'Generation error';
                    errorBox.classList.remove('d-none');
                } finally {
                    setLoading(false);
                }
            });
        })();
    </script>
</body>

</html>