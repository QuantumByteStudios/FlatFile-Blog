<?php

/**
 * FlatFile Blog - Homepage
 * Public listing of blog posts with Bootstrap layout
 */

// Check if blog is installed
if (!file_exists('config.php')) {
    header('Location: install.php');
    exit;
}

// Define constant to allow access
define('ALLOW_DIRECT_ACCESS', true);

try {
    require_once 'functions.php';
} catch (Exception $e) {
    error_log('Error loading functions.php: ' . $e->getMessage());
    $error_message = (defined('DEBUG_MODE') && constant('DEBUG_MODE'))
        ? 'Error loading functions: ' . $e->getMessage()
        : 'System error. Please try again later.';
    die($error_message);
}

// Load settings
$settings = load_settings();

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = $settings['posts_per_page'] ?? 10;

// Get posts
$posts = get_posts($page, $per_page);
$total_posts = count_posts();
$pagination = get_pagination_info($total_posts, $per_page, $page);

// Page title
$page_title = $settings['site_title'] ?? 'FlatFile Blog';
$site_description = $settings['site_description'] ?? 'A simple, fast, and secure flat-file blog system.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($site_description); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/main.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/blogs.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo BASE_URL; ?>">
                <?php echo SITE_TITLE; ?>
            </a>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">

                <h2 class="mb-4 fw-bold display-6">
                    <i class="bi bi-bookmarks-fill"></i>
                    Latest Posts
                </h2>

                <?php if (empty($posts)): ?>
                    <div class="alert py-5 mb-4">
                        <h4>No posts yet.</h4>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($posts as $post): ?>
                            <div class="col-lg-6 col-md-6 mb-4">
                                <article class="card blog-card h-100">
                                    <div class="card-header blog-card-header">
                                        <h3 class="blog-card-title">
                                            <a href="<?php echo BASE_URL; ?><?php echo urlencode($post['slug']); ?>">
                                                <?php echo htmlspecialchars($post['title']); ?>
                                            </a>
                                        </h3>
                                        <div class="blog-card-meta">
                                            <div class="blog-card-meta-left">
                                                <i class="bi bi-calendar"></i>
                                                <span><?php echo date('M j, Y', strtotime($post['date'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (!empty($post['meta']['image'])): ?>
                                        <div class="blog-card-image">
                                            <img src="<?php echo htmlspecialchars($post['meta']['image']); ?>"
                                                alt="<?php echo htmlspecialchars($post['title']); ?>"
                                                class="img-fluid">
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body blog-card-body">
                                        <?php if (!empty($post['excerpt'])): ?>
                                            <p class="blog-card-excerpt">
                                                <?php echo htmlspecialchars($post['excerpt']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($post['tags'])): ?>
                                            <div class="blog-card-tags">
                                                <?php foreach (array_slice($post['tags'], 0, 4) as $post_tag): ?>
                                                    <a href="<?php echo BASE_URL; ?>search?tag=<?php echo urlencode($post_tag); ?>" class="blog-card-tag">
                                                        <?php echo htmlspecialchars($post_tag); ?>
                                                    </a>
                                                <?php endforeach; ?>
                                                <?php if (count($post['tags']) > 4): ?>
                                                    <span class="blog-card-tag-more">
                                                        +<?php echo count($post['tags']) - 4; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($pagination['prev_page']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $pagination['prev_page']; ?>">Previous</a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">Previous</span>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($pagination['next_page']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $pagination['next_page']; ?>">Next</a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">Next</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light text-dark py-3 mt-5" style="bottom: 0; width: 100%;">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($page_title); ?>. All rights reserved.</span>
                    <br>
                    <span>
                        POWERED BY <a href="https://quantumbytestudios.in" style="border-bottom: 1px solid #000;" class="text-dark text-decoration-none">QUANTUM BYTE STUDIOS</a>
                    </span>
                    <br>
                    <span>
                        <a href="mailto:<?php echo htmlspecialchars($settings['admin_email']); ?>" style="border-bottom: 1px solid #000;" class="text-dark text-decoration-none"><?php echo htmlspecialchars($settings['admin_email']); ?></a>
                    </span>
                </div>
            </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>