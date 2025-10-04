<?php

/**
 * FlatFile Blog - Search Page
 * Dedicated search interface with advanced filtering
 */

require_once 'functions.php';

// Get search parameters
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$tag = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$author = isset($_GET['author']) ? trim($_GET['author']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;

// Get search results
$results = [];
$total_results = 0;

if (!empty($query)) {
    $results = search_posts($query);
} elseif (!empty($tag)) {
    $results = get_posts_by_tag($tag);
} elseif (!empty($category)) {
    $results = get_posts_by_category($category);
} elseif (!empty($author)) {
    $results = get_posts_by_author($author);
} elseif (!empty($date_from) || !empty($date_to)) {
    $results = get_posts_by_date_range($date_from, $date_to);
} else {
    $results = get_posts_by_status('published');
}

// Apply additional filters
if (!empty($author) && !empty($results)) {
    $results = array_filter($results, function ($post) use ($author) {
        return stripos($post['author'], $author) !== false;
    });
}

if (!empty($date_from) && !empty($results)) {
    $results = array_filter($results, function ($post) use ($date_from) {
        return strtotime($post['date']) >= strtotime($date_from);
    });
}

if (!empty($date_to) && !empty($results)) {
    $results = array_filter($results, function ($post) use ($date_to) {
        return strtotime($post['date']) <= strtotime($date_to . ' 23:59:59');
    });
}

$total_results = count($results);

// Apply pagination
$offset = ($page - 1) * $per_page;
$paginated_results = array_slice($results, $offset, $per_page);
$total_pages = ceil($total_results / $per_page);

// Get all tags and categories for filter options
$all_posts = get_posts_by_status('published');
$all_tags = [];
$all_categories = [];
$all_authors = [];

foreach ($all_posts as $post) {
    if (!empty($post['tags'])) {
        $all_tags = array_merge($all_tags, $post['tags']);
    }
    if (!empty($post['categories'])) {
        $all_categories = array_merge($all_categories, $post['categories']);
    }
    if (!empty($post['author'])) {
        $all_authors[] = $post['author'];
    }
}

$all_tags = array_unique($all_tags);
$all_categories = array_unique($all_categories);
$all_authors = array_unique($all_authors);
sort($all_tags);
sort($all_categories);
sort($all_authors);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - <?php echo SITE_TITLE; ?></title>
    <meta name="description" content="Search through our blog posts">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/main.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/blogs.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #000 0%, #333 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo BASE_URL; ?>">
                <?php echo SITE_TITLE; ?>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link fw-500" href="<?php echo BASE_URL; ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-500 active" href="<?php echo BASE_URL; ?>search">Search</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-light py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <div class="d-flex align-items-center justify-content-center mb-4">
                        <i class="bi bi-search me-3" style="font-size: 3rem; color: #000;"></i>
                        <div>
                            <h1 class="display-5 fw-bold mb-2">Search Posts</h1>
                            <p class="lead text-muted mb-0">Find exactly what you're looking for</p>
                        </div>
                    </div>

                    <!-- Quick Search Bar -->
                    <form method="GET" class="mb-4">
                        <div class="row justify-content-center">
                            <div class="col-lg-10">
                                <div class="input-group input-group-lg">
                                    <input type="text" class="form-control form-control-lg" name="q"
                                        value="<?php echo htmlspecialchars($query); ?>"
                                        placeholder="Search for posts, tags, authors..."
                                        style="border-radius: 50px 0 0 50px; border: 2px solid #000; box-shadow: none; font-size: 1.1rem; padding: 1rem 1.5rem;">
                                    <button class="btn btn-dark" type="submit" style="border-radius: 0 50px 50px 0; padding: 1rem 2.5rem; font-size: 1.1rem;">
                                        <i class="bi bi-search me-2"></i>Search
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Advanced Search Button -->
                    <div class="text-center">
                        <button type="button" class="btn btn-outline-dark btn-lg" data-bs-toggle="modal" data-bs-target="#advancedSearchModal" style="padding: 0.75rem 2rem; font-size: 1rem;">
                            <i class="bi bi-funnel me-2"></i>Advanced Search Filters
                        </button>
                    </div>

                    <?php if (!empty($query) || !empty($tag) || !empty($category) || !empty($author)): ?>
                        <div class="alert alert-dark border-0 mt-4" role="alert" style="border-radius: 15px;">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="bi bi-info-circle me-2"></i>
                                <div class="text-center">
                                    <?php if (!empty($query)): ?>
                                        <strong><?php echo $total_results; ?></strong> result(s) found for "<strong><?php echo htmlspecialchars($query); ?></strong>"
                                    <?php elseif (!empty($tag)): ?>
                                        <strong><?php echo $total_results; ?></strong> post(s) tagged with "<strong><?php echo htmlspecialchars($tag); ?></strong>"
                                    <?php elseif (!empty($category)): ?>
                                        <strong><?php echo $total_results; ?></strong> post(s) in category "<strong><?php echo htmlspecialchars($category); ?></strong>"
                                    <?php elseif (!empty($author)): ?>
                                        <strong><?php echo $total_results; ?></strong> post(s) by "<strong><?php echo htmlspecialchars($author); ?></strong>"
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8">

                <!-- Search Results Header -->
                <?php if (!empty($paginated_results)): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="h4 fw-bold mb-0">
                            <i class="bi bi-list-ul me-2"></i>Search Results
                        </h3>
                        <span class="badge bg-dark fs-6"><?php echo count($paginated_results); ?> of <?php echo $total_results; ?> results</span>
                    </div>
                <?php endif; ?>

                <!-- Search Results -->
                <?php if (empty($paginated_results)): ?>
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-search" style="font-size: 4rem; color: #ccc;"></i>
                        </div>
                        <h4 class="fw-bold mb-3">No Results Found</h4>
                        <p class="text-muted mb-4">
                            <?php if (!empty($query) || !empty($tag) || !empty($category) || !empty($author)): ?>
                                No posts found matching your search criteria. Try adjusting your filters or search terms.
                            <?php else: ?>
                                Enter search criteria above to find posts.
                            <?php endif; ?>
                        </p>
                        <a href="<?php echo BASE_URL; ?>search" class="btn btn-dark">
                            <i class="bi bi-arrow-clockwise me-2"></i>Start New Search
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($paginated_results as $post): ?>
                            <div class="col-lg-6 col-md-6 mb-4">
                                <article class="card blog-card h-100">
                                    <!-- Card Header -->
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

                                    <!-- Featured Image -->
                                    <?php if (!empty($post['meta']['image'])): ?>
                                        <div class="blog-card-image">
                                            <img src="<?php echo htmlspecialchars($post['meta']['image']); ?>"
                                                alt="<?php echo htmlspecialchars($post['title']); ?>"
                                                class="img-fluid">
                                        </div>
                                    <?php endif; ?>

                                    <!-- Card Body -->
                                    <div class="card-body blog-card-body">
                                        <?php if (!empty($post['excerpt'])): ?>
                                            <p class="blog-card-excerpt">
                                                <?php echo htmlspecialchars($post['excerpt']); ?>
                                            </p>
                                        <?php endif; ?>

                                        <!-- Tags -->
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

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Search results pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            Next <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Search Tips -->
                <div class="card mb-4" style="border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 15px;">
                    <div class="card-header" style="background: linear-gradient(135deg, #000 0%, #333 100%); color: white; border-radius: 15px 15px 0 0; border: none;">
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-lightbulb me-2"></i>Search Tips
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-3 d-flex align-items-start">
                                <i class="bi bi-check-circle text-success me-2 mt-1"></i>
                                <span class="small">Use specific keywords to find relevant posts</span>
                            </li>
                            <li class="mb-3 d-flex align-items-start">
                                <i class="bi bi-check-circle text-success me-2 mt-1"></i>
                                <span class="small">Filter by tags and categories for better results</span>
                            </li>
                            <li class="mb-3 d-flex align-items-start">
                                <i class="bi bi-check-circle text-success me-2 mt-1"></i>
                                <span class="small">Search by author name to find their posts</span>
                            </li>
                            <li class="mb-0 d-flex align-items-start">
                                <i class="bi bi-check-circle text-success me-2 mt-1"></i>
                                <span class="small">Use date ranges for time-based searches</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Popular Tags -->
                <div class="card mb-4" style="border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 15px;">
                    <div class="card-header" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 15px 15px 0 0; border: none;">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-tags me-2"></i>Popular Tags
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($all_tags)): ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach (array_slice($all_tags, 0, 10) as $tag): ?>
                                    <a href="<?php echo BASE_URL; ?>search?tag=<?php echo urlencode($tag); ?>"
                                        class="badge bg-dark text-decoration-none" style="font-size: 0.8rem;">
                                        <?php echo htmlspecialchars($tag); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No tags available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_TITLE; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="d-flex align-items-center justify-content-end">
                        <span class="text-muted me-2">Powered by</span>
                        <img src="https://quantumbytestudios.in/src/images/white_transparent.png"
                            alt="QuantumByte Studios"
                            style="height: 24px; width: auto;">
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Advanced Search Modal -->
    <div class="modal fade" id="advancedSearchModal" tabindex="-1" aria-labelledby="advancedSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 15px; border: none;">
                <div class="modal-header" style="background: linear-gradient(135deg, #000 0%, #333 100%); color: white; border-radius: 15px 15px 0 0; border: none;">
                    <h5 class="modal-title fw-bold" id="advancedSearchModalLabel">
                        <i class="bi bi-funnel me-2"></i>Advanced Search Filters
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="GET" class="row g-4">
                        <div class="col-md-6">
                            <label for="modal_q" class="form-label fw-bold">Search Query</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" id="modal_q" name="q"
                                    value="<?php echo htmlspecialchars($query); ?>"
                                    placeholder="Enter search terms...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_tag" class="form-label fw-bold">Tag</label>
                            <select class="form-select" id="modal_tag" name="tag">
                                <option value="">All Tags</option>
                                <?php foreach ($all_tags as $tag_option): ?>
                                    <option value="<?php echo htmlspecialchars($tag_option); ?>"
                                        <?php echo $tag === $tag_option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tag_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_category" class="form-label fw-bold">Category</label>
                            <select class="form-select" id="modal_category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($all_categories as $category_option): ?>
                                    <option value="<?php echo htmlspecialchars($category_option); ?>"
                                        <?php echo $category === $category_option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_author" class="form-label fw-bold">Author</label>
                            <select class="form-select" id="modal_author" name="author">
                                <option value="">All Authors</option>
                                <?php foreach ($all_authors as $author_option): ?>
                                    <option value="<?php echo htmlspecialchars($author_option); ?>"
                                        <?php echo $author === $author_option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($author_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_date_from" class="form-label fw-bold">From Date</label>
                            <input type="date" class="form-control" id="modal_date_from" name="date_from"
                                value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="modal_date_to" class="form-label fw-bold">To Date</label>
                            <input type="date" class="form-control" id="modal_date_to" name="date_to"
                                value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border: none; padding: 1rem 1.5rem 1.5rem;">
                    <div class="d-flex gap-3 w-100">
                        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-dark" onclick="submitAdvancedSearch()">
                            <i class="bi bi-search me-2"></i>Search
                        </button>
                        <a href="<?php echo BASE_URL; ?>search" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-2"></i>Clear All
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function submitAdvancedSearch() {
            const form = document.querySelector('#advancedSearchModal form');
            form.submit();
        }
    </script>
</body>

</html>