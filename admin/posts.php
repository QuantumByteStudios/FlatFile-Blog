<?php
declare(strict_types=1);

/**
 * Admin — All posts
 */

$config_path = file_exists('../config.php') ? '../config.php' : 'config.php';
if (!file_exists($config_path)) {
    header('Location: ../install.php');
    exit;
}

define('ALLOW_DIRECT_ACCESS', true);

try {
    $functions_path = file_exists('../functions.php') ? '../functions.php' : 'functions.php';
    require_once $functions_path;
} catch (Exception $e) {
    error_log('Error loading functions.php: ' . $e->getMessage());
    die('System error. Please try again later.');
}

$security_hardener_path = __DIR__ . '/../libs/SecurityHardener.php';
if (file_exists($security_hardener_path)) {
    require_once $security_hardener_path;
    if (class_exists('SecurityHardener')) {
        SecurityHardener::init();
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login');
    exit;
}

clearstatcache(true);
$all_posts = all_posts();
$search_query = trim((string) ($_GET['q'] ?? ''));
$view_mode = trim((string) ($_GET['view'] ?? 'all'));
if (!in_array($view_mode, ['all', 'scheduled'], true)) {
    $view_mode = 'all';
}

$current_ts = time();
$scheduled_posts = array_values(array_filter($all_posts, static function ($post) use ($current_ts) {
    $post_date = isset($post['date']) ? strtotime((string) $post['date']) : 0;
    return (($post['status'] ?? '') === 'published') && ($post_date > $current_ts);
}));

$filtered_posts = $view_mode === 'scheduled' ? $scheduled_posts : $all_posts;
if ($search_query !== '') {
    $query_lc = mb_strtolower($search_query);
    $filtered_posts = array_values(array_filter($filtered_posts, static function ($post) use ($query_lc) {
        $title = mb_strtolower((string) ($post['title'] ?? ''));
        return $title !== '' && strpos($title, $query_lc) !== false;
    }));
}

$total_posts = count($all_posts);
$display_count = count($filtered_posts);
$scheduled_count = count($scheduled_posts);

$success_message = '';
$error_message = '';
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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title>All Posts - <?php echo SITE_TITLE; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/main.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>admin/assets/css/admin.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 bg-dark min-vh-100 p-0">
                <div class="p-3">
                    <center>
                        <h5 class="text-light mb-4">
                            <a href="." class="text-light text-decoration-none">Admin Panel</a>
                        </h5>
                    </center>
                    <nav class="nav flex-column">
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                        <a class="nav-link text-light active" href="<?php echo BASE_URL; ?>admin/posts">
                            <i class="bi bi-journal-text"></i> All Posts
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

            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <div class="bg-dark text-white p-3 rounded">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <h1 class="h3 mb-1 fw-bold text-white">All Posts</h1>
                                    <p class="text-white-50 mb-0 small"><?php echo $display_count; ?> shown / <?php echo $total_posts; ?> total</p>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="<?php echo BASE_URL; ?>admin" class="btn btn-outline-light btn-sm">
                                        <i class="bi bi-arrow-left me-1"></i>Dashboard
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>admin/posts?view=scheduled"
                                        class="btn btn-outline-light btn-sm <?php echo $view_mode === 'scheduled' ? 'active' : ''; ?>">
                                        <i class="bi bi-clock-history me-1"></i>Scheduled (<?php echo $scheduled_count; ?>)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="GET" class="mb-4">
                        <div class="row g-2">
                            <div class="col-md-7">
                                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="Search blogs by title">
                            </div>
                            <div class="col-md-3">
                                <select name="view" class="form-select">
                                    <option value="all" <?php echo $view_mode === 'all' ? 'selected' : ''; ?>>All Posts</option>
                                    <option value="scheduled" <?php echo $view_mode === 'scheduled' ? 'selected' : ''; ?>>Scheduled Only</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-dark">
                                    <i class="bi bi-search me-1"></i>Search
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="admin-posts-page border rounded bg-white shadow-sm">
                        <?php if (!empty($filtered_posts)): ?>
                            <?php foreach ($filtered_posts as $post):
                                $post_date = isset($post['date']) ? strtotime($post['date']) : 0;
                                $is_scheduled = ($post['status'] ?? '') === 'published' && $post_date > time();
                                if ($is_scheduled) {
                                    $pill_class = 'text-bg-info';
                                    $badge_text = 'Scheduled';
                                } elseif (($post['status'] ?? '') === 'published') {
                                    $pill_class = 'text-bg-success';
                                    $badge_text = 'Published';
                                } else {
                                    $pill_class = 'text-bg-warning';
                                    $badge_text = 'Draft';
                                }
                                $full_title = $post['title'] ?? '';
                                ?>
                                <article class="admin-post-row">
                                    <div class="admin-post-row__body">
                                        <div class="admin-post-row__title">
                                            <a href="<?php echo BASE_URL; ?><?php echo urlencode($post['slug']); ?>"
                                                target="_blank" rel="noopener"
                                                title="<?php echo htmlspecialchars($full_title); ?>">
                                                <?php echo htmlspecialchars($full_title); ?>
                                            </a>
                                        </div>
                                        <p class="admin-post-row__meta text-muted small mb-0">
                                            <?php echo date('M j, Y', strtotime($post['date'])); ?>
                                            <span class="mx-1">•</span>
                                            By <?php echo htmlspecialchars($post['author'] ?? 'Admin'); ?>
                                        </p>
                                    </div>
                                    <div class="admin-post-row__aside">
                                        <span class="badge rounded-pill <?php echo $pill_class; ?>"><?php echo htmlspecialchars($badge_text); ?></span>
                                        <div class="btn-group" role="group">
                                            <a href="<?php echo BASE_URL; ?><?php echo urlencode($post['slug']); ?>"
                                                class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit-post?slug=<?php echo htmlspecialchars(urlencode($post['slug']), ENT_QUOTES, 'UTF-8'); ?>"
                                                class="btn btn-sm btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete-post?slug=<?php echo htmlspecialchars(urlencode($post['slug']), ENT_QUOTES, 'UTF-8'); ?>"
                                                class="btn btn-sm btn-outline-secondary"
                                                onclick="return confirm('Are you sure you want to delete this post?');" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5 px-3">
                                <i class="bi bi-file-text text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3 mb-3">No posts found for this filter/search</p>
                                <a href="new-post" class="btn btn-dark btn-sm">Create your first post</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
