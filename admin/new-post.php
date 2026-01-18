<?php

/**
 * Create New Post
 */

// Check if blog is installed
$config_path = file_exists(__DIR__ . '/../config.php') ? __DIR__ . '/../config.php' : '../config.php';
if (!file_exists($config_path)) {
    header('Location: ../install.php');
    exit;
}

// Define constant to allow access
define('ALLOW_DIRECT_ACCESS', true);

try {
    $functions_path = file_exists(__DIR__ . '/../functions.php') ? __DIR__ . '/../functions.php' : '../functions.php';
    require_once $functions_path;
} catch (Exception $e) {
    error_log('Error loading functions.php: ' . $e->getMessage());
    die('System error. Please try again later.');
}

// Load SecurityHardener if available
$security_hardener_path = __DIR__ . '/../libs/SecurityHardener.php';
if (file_exists($security_hardener_path)) {
    require_once $security_hardener_path;
    // Initialize security system
    if (class_exists('SecurityHardener')) {
        SecurityHardener::init();
    }
}

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

$success_message = '';
$error_message = '';

// Ensure CSRF token exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle success/error messages from URL parameters
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars(urldecode($_GET['success']), ENT_QUOTES, 'UTF-8');
}
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars(urldecode($_GET['error']), ENT_QUOTES, 'UTF-8');
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
                    <div class="mb-5">
                        <div class="bg-dark text-white p-3 rounded mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h1 class="h3 mb-0 fw-bold text-white">New Post</h1>
                                <a href="<?php echo BASE_URL; ?>admin" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                                </a>
                            </div>
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
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="row g-4">
                            <div class="col-lg-8">
                                <!-- Main Content -->
                                <div class="mb-4">
                                    <div class="bg-dark text-white p-3 rounded mb-4 d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0 fw-semibold text-white">
                                            <i class="bi bi-file-text me-2"></i>Post Content
                                        </h5>
                                        <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#aiModal">
                                            <i class="bi bi-stars me-1"></i>Generate with AI
                                        </button>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="title" class="form-label fw-medium">Title *</label>
                                        <input type="text" class="form-control" id="title" name="title"
                                            value="<?php echo htmlspecialchars($title ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-4">
                                        <label for="slug" class="form-label fw-medium">Slug</label>
                                        <input type="text" class="form-control" id="slug" name="slug"
                                            value="<?php echo htmlspecialchars($slug ?? ''); ?>"
                                            placeholder="auto-generated from title">
                                    </div>

                                    <div class="mb-4">
                                        <label for="excerpt" class="form-label fw-medium">Excerpt</label>
                                        <textarea class="form-control" id="excerpt" name="excerpt" rows="3"
                                            placeholder="Brief description of the post"><?php echo htmlspecialchars($excerpt ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-4">
                                        <label for="content_type" class="form-label fw-medium">Content Type</label>
                                        <select class="form-select" id="content_type" name="content_type"
                                            onchange="toggleContentType()">
                                            <option value="html" <?php echo ($content_type ?? 'html') === 'html' ? 'selected' : ''; ?>>HTML</option>
                                            <option value="markdown" <?php echo ($content_type ?? 'html') === 'markdown' ? 'selected' : ''; ?>>Markdown</option>
                                        </select>
                                    </div>

                                    <div class="mb-4">
                                        <label for="content" class="form-label fw-medium">Content *</label>
                                        <textarea class="form-control" id="content" name="content" rows="15"
                                            required
                                            placeholder="Write your post content..."><?php echo htmlspecialchars($content ?? ''); ?></textarea>
                                        <div class="form-text text-muted small mt-1" id="content-help">
                                            <strong>Markdown supported:</strong> Use **bold**, *italic*, `code`,
                                            [links](url), # headers, etc.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <!-- Publish Settings -->
                                <div class="mb-4">
                                    <div class="bg-dark text-white p-3 rounded mb-4">
                                        <h5 class="mb-0 fw-semibold text-white">
                                            <i class="bi bi-gear me-2"></i>Publish Settings
                                        </h5>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="status" class="form-label fw-medium">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?php echo ($status ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="published" <?php echo ($status ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                                        </select>
                                    </div>

                                    <div class="mb-4">
                                        <label for="author" class="form-label fw-medium">Author</label>
                                        <input type="text" class="form-control" id="author" name="author"
                                            value="<?php echo htmlspecialchars($author ?? 'Admin'); ?>">
                                    </div>

                                    <div class="mb-4">
                                        <label for="date" class="form-label fw-medium">Publish Date/Time</label>
                                        <input type="datetime-local" class="form-control" id="date" name="date"
                                            value="<?php echo htmlspecialchars(isset($date) ? date('Y-m-d\\TH:i', strtotime($date)) : date('Y-m-d\\TH:i')); ?>">
                                        <div class="form-text text-muted small mt-1">Leave as-is for current time.</div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="updated" class="form-label fw-medium">Last Edited Time</label>
                                        <input type="datetime-local" class="form-control" id="updated"
                                            name="updated"
                                            value="<?php echo htmlspecialchars(isset($date) ? date('Y-m-d\\TH:i', strtotime($date)) : date('Y-m-d\\TH:i')); ?>">
                                        <div class="form-text text-muted small mt-1">Defaults to the publish time.</div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="featured_image" class="form-label fw-medium">Featured Image</label>
                                        <input type="file" class="form-control" id="featured_image"
                                            name="featured_image" accept="image/*">
                                        <div class="form-text text-muted small mt-1">Upload a featured image for this post (JPG, PNG, GIF, WebP)</div>
                                    </div>
                                </div>

                                <!-- Tags & Categories -->
                                <div class="mb-4">
                                    <div class="bg-dark text-white p-3 rounded mb-4">
                                        <h5 class="mb-0 fw-semibold text-white">
                                            <i class="bi bi-tags me-2"></i>Tags & Categories
                                        </h5>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="tags" class="form-label fw-medium">Tags</label>
                                        <input type="text" class="form-control" id="tags" name="tags"
                                            value="<?php echo htmlspecialchars(implode(', ', $tags ?? [])); ?>"
                                            placeholder="tag1, tag2, tag3">
                                    </div>

                                    <div class="mb-4">
                                        <label for="categories" class="form-label fw-medium">Categories</label>
                                        <input type="text" class="form-control" id="categories" name="categories"
                                            value="<?php echo htmlspecialchars(implode(', ', $categories ?? [])); ?>"
                                            placeholder="category1, category2">
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="border-top pt-4">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-dark btn-sm">
                                            <i class="bi bi-check-circle me-2"></i>Create Post
                                        </button>
                                        <a href="<?php echo BASE_URL; ?>admin" class="btn btn-outline-dark btn-sm">
                                            <i class="bi bi-x-circle me-2"></i>Cancel
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- AI Modal -->
                    <div class="modal fade" id="aiModal" tabindex="-1" aria-labelledby="aiModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="aiModalLabel"><i class="bi bi-stars"></i> Generate Blog
                                        with AI</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="ai_topic" class="form-label">Topic</label>
                                        <input type="text" class="form-control" id="ai_topic"
                                            placeholder="e.g. Local SEO tips for dentists">
                                        <div class="form-text">Describe the topic you want a blog about.</div>
                                    </div>
                                    <div id="ai_error" class="alert alert-danger d-none" role="alert"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Close</button>
                                    <button type="button" id="ai_generate_btn" class="btn btn-primary">
                                        <span class="spinner-border spinner-border-sm d-none" id="ai_spinner"
                                            role="status" aria-hidden="true"></span>
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
        document.getElementById('title').addEventListener('input', function () {
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
                contentHelp.innerHTML = '<strong>HTML supported:</strong> Use only &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;br&gt;.';
                contentTextarea.placeholder = 'Write your post content in HTML...';
            } else {
                contentHelp.innerHTML = '<strong>Markdown supported:</strong> Use **bold**, *italic*, `code`, [links](url), # headers, etc.';
                contentTextarea.placeholder = 'Write your post content in Markdown...';
            }
        }
        document.addEventListener('DOMContentLoaded', toggleContentType);

        // AI Generate flow
        (function () {
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

            btn.addEventListener('click', async function () {
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
                    const url = '<?php echo BASE_URL; ?>admin_action?action=generate_ai_post&topic=' + encodeURIComponent(topic);
                    const resp = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    });

                    // Read raw response text first for better debugging
                    const rawText = await resp.text();
                    let data;
                    try {
                        data = rawText ? JSON.parse(rawText) : null;
                    } catch (parseErr) {
                        // Log detailed info for debugging
                        try {
                            const headersObj = {};
                            resp.headers.forEach((v, k) => headersObj[k] = v);
                            console.error('AI generate: Failed to parse JSON.', {
                                url,
                                status: resp.status,
                                ok: resp.ok,
                                headers: headersObj,
                                body: rawText
                            });
                        } catch (_) {
                            console.error('AI generate: Failed to parse JSON. Raw body:', rawText);
                        }
                        throw new Error('Failed to parse AI response');
                    }

                    if (!data.success) {
                        console.error('AI generate: Server returned error payload:', data);
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
                    console.error('AI generate: Request failed.', e);
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