<?php
declare(strict_types=1);

/**
 * FlatFile Blog - Single Post View
 * Displays individual blog posts with Markdown rendering
 */

// Check if blog is installed
if (!file_exists('config.php')) {
	header('Location: install.php');
	exit;
}

// Define constant to allow access
define('ALLOW_DIRECT_ACCESS', true);

try {
	require_once 'config.php';
	require_once 'functions.php';
} catch (Exception $e) {
	error_log('Error loading functions.php: ' . $e->getMessage());
	die('System error. Please try again later.');
}

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Load settings for footer/contact info
$settings = load_settings();
$site_title = $settings['site_title'] ?? SITE_TITLE ?? 'FlatFile Blog';
$blogs_list_url = absolute_url('blogs');
$interface_mode = defined('INTERFACE_MODE') ? (string) constant('INTERFACE_MODE') : ($settings['interface_mode'] ?? 'classic');

if ($interface_mode === 'custom') {
	header('HTTP/1.1 404 Not Found');
	header('Content-Type: text/html; charset=utf-8');
	?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Custom Interface Mode Enabled</title>
	</head>
	<body>
		<h1>Custom Interface Mode Enabled</h1>
		<p>The built-in post view page is disabled.</p>
		<p>Use `rss.php` JSON output and build your own post page UI.</p>
		<p>
			<a href="<?php echo htmlspecialchars(BASE_URL . 'admin/', ENT_QUOTES, 'UTF-8'); ?>">
				Open Admin Panel
			</a>
		</p>
	</body>
	</html>
	<?php
	exit;
}

// Get post slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
	header('HTTP/1.0 404 Not Found');
	include '404.php';
	exit;
}

// Load the post
$post = get_post($slug);

if (!$post) {
	header('HTTP/1.0 404 Not Found');
	include '404.php';
	exit;
}

// Check if post is published (unless admin)
if ($post['status'] !== 'published') {
	header('HTTP/1.0 404 Not Found');
	include '404.php';
	exit;
}

// Check if post is scheduled for future (scheduling feature)
// If post date is in the future, don't show it to public
$post_date = isset($post['date']) ? strtotime($post['date']) : 0;
$current_time = time();
$is_admin_preview = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if ($post_date > $current_time && !$is_admin_preview) {
	// Post is scheduled for future - show 404 to public
	header('HTTP/1.0 404 Not Found');
	include '404.php';
	exit;
}

// Determine content type and render accordingly
$content_type = $post['content_type'] ?? (isset($post['content_markdown']) ? 'markdown' : 'html');

if ($content_type === 'html') {
	$raw_content = $post['content_html'] ?? $post['content'] ?? '';
	// Use HTML content directly
	$html_content = $raw_content;
} else {
	$raw_content = $post['content_markdown'] ?? $post['content'] ?? '';
	// Render Markdown content (if Parsedown is available)
	if (file_exists('libs/Parsedown.php')) {
		require_once 'libs/Parsedown.php';
		$parsedown = new Parsedown();
		$parsedown->setBreaksEnabled(true);
		$parsedown->setUrlsLinked(true);
		$html_content = $parsedown->text($raw_content);
	} else {
		// Fallback to raw content if Parsedown not available
		$html_content = nl2br(htmlspecialchars($raw_content));
	}
}

// Secure HTML sanitization for both HTML and Markdown content (if available)
if (file_exists('libs/HTMLSanitizer.php')) {
	require_once 'libs/HTMLSanitizer.php';
	$html_content = HTMLSanitizer::sanitize($html_content);
}

// Set caching headers
$last_modified = strtotime($post['updated']);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
header('Cache-Control: public, max-age=3600'); // 1 hour cache

$toc_result = build_table_of_contents($html_content);
$html_content = $toc_result['html'];
$table_of_contents = $toc_result['toc'];

$page_title = post_meta_title($post) . ' - ' . SITE_TITLE;
$page_description = post_meta_description($post, $html_content);
$canonical_url = post_canonical_url($post);
$og_title = post_og_title($post);
$og_description = post_og_description($post, $html_content);
$og_image = post_og_image($post);
$robots_index = post_robots_index($post);
$read_time = $post['read_time'] ?? post_read_time_minutes($post);
$word_count = $post['word_count'] ?? post_word_count($post);
$related_posts = get_related_posts($post, 3);
$favicon = site_favicon_url();
$schema_settings = $post['schema'] ?? [];
$show_article_schema = !isset($schema_settings['article']) || $schema_settings['article'] !== false;
$show_breadcrumb_schema = !isset($schema_settings['breadcrumb']) || $schema_settings['breadcrumb'] !== false;
$faq_schema = $schema_settings['faq'] ?? [];
$share_url = rawurlencode($canonical_url);
$share_title = rawurlencode($post['title']);

?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($page_title); ?></title>
	<meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
	<meta name="author" content="<?php echo htmlspecialchars($post['author']); ?>">
	<?php if (!$robots_index): ?>
		<meta name="robots" content="noindex, nofollow">
	<?php endif; ?>
	<?php if ($favicon !== ''): ?>
		<link rel="icon" href="<?php echo htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8'); ?>">
	<?php endif; ?>

	<link rel="canonical" href="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">

	<meta property="og:title" content="<?php echo htmlspecialchars($og_title); ?>">
	<meta property="og:description" content="<?php echo htmlspecialchars($og_description); ?>">
	<meta property="og:type" content="article">
	<meta property="og:url" content="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
	<meta property="og:site_name" content="<?php echo SITE_TITLE; ?>">
	<?php if ($og_image !== ''): ?>
		<meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
	<?php endif; ?>

	<?php if (post_use_twitter_card($post)): ?>
		<meta name="twitter:card" content="summary_large_image">
		<meta name="twitter:title" content="<?php echo htmlspecialchars($og_title); ?>">
		<meta name="twitter:description" content="<?php echo htmlspecialchars($og_description); ?>">
		<?php if ($og_image !== ''): ?>
			<meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">
		<?php endif; ?>
	<?php endif; ?>

	<!-- Article Meta -->
	<meta property="article:published_time" content="<?php echo date('c', strtotime($post['date'])); ?>">
	<meta property="article:modified_time" content="<?php echo date('c', strtotime($post['updated'])); ?>">
	<meta property="article:author" content="<?php echo htmlspecialchars($post['author']); ?>">
	<?php if (!empty($post['tags'])): ?>
		<?php foreach ($post['tags'] as $tag): ?>
			<meta property="article:tag" content="<?php echo htmlspecialchars($tag); ?>">
		<?php endforeach; ?>
	<?php endif; ?>

	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
	<!-- Blog CSS -->
	<link href="<?php echo BASE_URL; ?>assets/css/blogs.css" rel="stylesheet">

</head>

<body>
	<!-- Navigation -->
	<nav class="navbar navbar-expand-lg navbar-light bg-light">
		<div class="container">
			<a class="navbar-brand fw-bold" href="<?php echo BASE_URL; ?>">
				<?php echo SITE_TITLE; ?>
			</a>
			<div class="ms-auto">
				<a class="nav-link d-inline-block py-0 fw-semibold" href="<?php echo htmlspecialchars($blogs_list_url); ?>">All Blogs</a>
			</div>
		</div>
	</nav>

	<!-- Main Content -->
	<div class="container mt-5">
		<div class="row justify-content-center">
			<div class="col-lg-8">

				<?php echo render_post_breadcrumbs($post); ?>

				<p class="text-muted small mb-3">
					<i class="bi bi-clock"></i> <?php echo (int) $read_time; ?> min read
					&middot; <?php echo (int) $word_count; ?> words
					&middot; <?php echo htmlspecialchars($post['author']); ?>
				</p>

				<!-- Share Icons -->
				<div class="mb-3 d-flex gap-3 align-items-center flex-wrap">
					<a href="https://wa.me/?text=<?php echo $share_title; ?>%20<?php echo $share_url; ?>"
						class="text-dark" target="_blank" rel="noopener" title="Share on WhatsApp">WhatsApp</a>
					<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>"
						class="text-dark" target="_blank" rel="noopener" title="Share on Facebook">Facebook</a>
					<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $share_url; ?>&amp;title=<?php echo $share_title; ?>"
						class="text-dark" target="_blank" rel="noopener" title="Share on LinkedIn"
						style="line-height:1;">
						<svg width="28" height="28" class="linkedin" viewBox="0 0 28 28"
							xmlns="http://www.w3.org/2000/svg">
							<g clip-path="url(#clip0_3302_57044)">
								<path
									d="M24.889 0H3.11C2.7015 0 2.29701 0.0805 1.91962 0.2368C1.54224 0.3932 1.19935 0.6224 0.910544 0.9113C0.621741 1.2002 0.392681 1.5431 0.236448 1.9206C0.0802144 2.298 -0.000131188 2.7025 0 3.111V24.89C0 25.2985 0.0804758 25.703 0.23683 26.0804C0.393185 26.4578 0.622355 26.8007 0.911252 27.0895C1.20015 27.3783 1.54311 27.6073 1.92055 27.7636C2.29799 27.9198 2.70251 28.0001 3.111 28H24.89C25.2985 28 25.703 27.9195 26.0804 27.7632C26.4578 27.6068 26.8007 27.3776 27.0895 27.0887C27.3783 26.7999 27.6073 26.4569 27.7636 26.0795C27.9198 25.702 28.0001 25.2975 28 24.889V3.11C28 2.7015 27.9195 2.29701 27.7632 1.91962C27.6068 1.54224 27.3776 1.19935 27.0887 0.910544C26.7999 0.621741 26.4569 0.392681 26.0795 0.236448C25.702 0.0802144 25.2975 -0.000131188 24.889 0ZM9.333 21.778H5.41V10.888H9.334V21.778H9.333ZM7.302 8.893C6.102 8.893 5.302 8.093 5.302 7.026C5.302 5.959 6.1 5.16 7.433 5.16C8.633 5.16 9.433 5.96 9.433 7.026C9.433 8.094 8.633 8.893 7.301 8.893H7.302ZM23.333 21.778H19.535V15.826C19.535 14.18 18.522 13.801 18.142 13.801C17.762 13.801 16.497 14.054 16.497 15.826V21.778H12.572V10.888H16.497V12.408C17.002 11.522 18.015 10.888 19.914 10.888C21.814 10.888 23.334 12.408 23.334 15.826V21.778H23.333Z">
								</path>
							</g>
							<defs>
								<clipPath id="clip0_3302_57044">
									<rect width="28" height="28" fill="white"></rect>
								</clipPath>
							</defs>
						</svg>
					</a>
					<a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&amp;text=<?php echo $share_title; ?>"
						class="text-dark" target="_blank" rel="noopener" title="Share on X" style="line-height:1;">
						<svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect width="28" height="28" rx="4" fill="#43414F"></rect>
							<path
								d="M19.9222 4.66675H23.0741L16.1883 12.5742L24.2888 23.3334H17.9457L12.9779 16.8085L7.2928 23.3334H4.13916L11.504 14.8763L3.7334 4.66675H10.237L14.7275 10.6315L19.9222 4.66675ZM18.8157 21.4381H20.5627L9.28848 6.46302H7.41409L18.8157 21.4381Z"
								fill="white"></path>
						</svg>
					</a>
					<span class="text-dark" title="Copy link" style="cursor:pointer;line-height:1;"
						onclick="navigator.clipboard.writeText('<?php echo $canonical_url; ?>'); this.firstElementChild.style.fill='#007bff'; this.title='Copied!'; setTimeout(()=>{this.firstElementChild.style.fill=''; this.title='Copy link';},1500);">
						<svg width="28" height="28" class="link" viewBox="0 0 28 14" xmlns="http://www.w3.org/2000/svg">
							<path
								d="M6.364 0.5C2.85 0.5 0 3.41 0 7C0 10.59 2.85 13.5 6.364 13.5H11.454V10.9H6.364C4.254 10.9 2.545 9.154 2.545 7C2.545 4.846 4.255 3.1 6.364 3.1H11.454V0.5H6.364V0.5ZM16.545 0.5V3.1H21.636C23.746 3.1 25.455 4.846 25.455 7C25.455 9.154 23.745 10.9 21.636 10.9H16.546V13.5H21.636C25.15 13.5 28 10.59 28 7C28 3.41 25.15 0.5 21.636 0.5H16.546H16.545ZM7.636 5.7V8.3H20.364V5.7H7.636Z">
							</path>
						</svg>
					</span>
				</div>

				<!-- Post Header -->
				<header class="mb-4">
					<h1 class="display-4 fw-bold">
						<?php echo htmlspecialchars($post['title']); ?>
					</h1>

					<!-- Featured Image -->
					<?php if (!empty($post['meta']['image'])): ?>
						<div class="featured-image mb-4">
							<img src="<?php echo htmlspecialchars($post['meta']['image']); ?>"
								alt="<?php echo htmlspecialchars($post['meta']['image_alt'] ?? $post['title']); ?>" class="img-fluid">
						</div>
					<?php endif; ?>

					<!-- <div class="post-meta mb-3">
						<div class="row">
							<div class="col-md-6">
								<i class="bi bi-calendar"></i>
								Published: <?php echo date('F j, Y', strtotime($post['date'])); ?>
							</div>
							<div class="col-md-6">
								<i class="bi bi-person"></i>
								By <?php echo htmlspecialchars($post['author']); ?>
							</div>
						</div>
						<?php if ($post['updated'] !== $post['date']): ?>
							<div class="mt-1">
								<i class="bi bi-pencil"></i>
								Updated: <?php echo date('F j, Y', strtotime($post['updated'])); ?>
							</div>
						<?php endif; ?>
					</div> -->
				</header>

				<?php if ($table_of_contents !== ''): ?>
					<?php echo $table_of_contents; ?>
				<?php endif; ?>

				<!-- Post Content -->
				<article class="post-content">
					<?php echo $html_content; ?>
				</article>

				<?php if ($related_posts !== []): ?>
					<section class="mt-5 pt-4 border-top">
						<h2 class="h5 mb-3">Related posts</h2>
						<ul class="list-unstyled">
							<?php foreach ($related_posts as $rp): ?>
								<li class="mb-2">
									<a href="<?php echo htmlspecialchars(absolute_url(rawurlencode($rp['slug'])), ENT_QUOTES, 'UTF-8'); ?>">
										<?php echo htmlspecialchars($rp['title'] ?? $rp['slug']); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<!-- Tags and Categories (moved to end) -->
				<?php if (!empty($post['tags']) || !empty($post['categories'])): ?>
					<div class="mt-5 pt-4 border-top">
						<?php if (!empty($post['tags'])): ?>
							<div class="mb-3">
								Tags<br>
								<?php foreach ($post['tags'] as $tag): ?>
									<a href="<?php echo BASE_URL; ?>?tag=<?php echo urlencode($tag); ?>" class="tag-badge">
										<?php echo htmlspecialchars($tag); ?>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<?php if (!empty($post['categories'])): ?>
							<div class="mb-3">
								Categories<br>
								<?php foreach ($post['categories'] as $category): ?>
									<a href="<?php echo BASE_URL; ?>?category=<?php echo urlencode($category); ?>"
										class="tag-badge">
										<?php echo htmlspecialchars($category); ?>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- Post Footer -->
				<footer class="mt-5 pt-4 border-top">
					<div class="row">
						<div class="col-md-12">
							<p class="text-muted">
								<small>
									Published on <?php echo date('F j, Y \a\t g:i A', strtotime($post['date'])); ?>
									<?php if ($post['updated'] !== $post['date']): ?>
										• Updated on <?php echo date('F j, Y \a\t g:i A', strtotime($post['updated'])); ?>
									<?php endif; ?>
								</small>
							</p>
							<a href="<?php echo htmlspecialchars($blogs_list_url); ?>" class="btn btn-sm btn-outline-dark">
								<i class="bi bi-arrow-left"></i> Back to All Blogs
							</a>
						</div>
					</div>
				</footer>

			</div>
		</div>
	</div>

	<!-- Footer -->
	<footer class="bg-light text-dark py-3 mt-5" style="bottom: 0; width: 100%;">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_title); ?>. All rights
						reserved.</span>
					<br>
					<span>
						POWERED BY <a href="https://quantumbytestudios.in" style="border-bottom: 1px solid #000;"
							class="text-dark text-decoration-none">QUANTUM BYTE STUDIOS</a>
					</span>
					<br>
					<span>
						<a href="mailto:<?php echo htmlspecialchars($settings['admin_email']); ?>"
							style="border-bottom: 1px solid #000;"
							class="text-dark text-decoration-none"><?php echo htmlspecialchars($settings['admin_email']); ?></a>
					</span>
				</div>
			</div>
	</footer>

	<?php if ($show_article_schema): ?>
	<script type="application/ld+json"><?php echo json_encode([
		'@context' => 'https://schema.org',
		'@type' => 'BlogPosting',
		'headline' => $post['title'],
		'description' => $page_description,
		'image' => $og_image !== '' ? $og_image : null,
		'author' => ['@type' => 'Person', 'name' => $post['author']],
		'publisher' => ['@type' => 'Organization', 'name' => SITE_TITLE],
		'datePublished' => date('c', strtotime($post['date'])),
		'dateModified' => date('c', strtotime($post['updated'])),
		'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical_url],
		'url' => $canonical_url,
		'keywords' => !empty($post['tags']) ? implode(', ', $post['tags']) : '',
		'articleSection' => !empty($post['categories']) ? implode(', ', $post['categories']) : 'General',
		'wordCount' => $word_count
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
	<?php endif; ?>

	<?php if ($show_breadcrumb_schema): ?>
	<script type="application/ld+json"><?php echo json_encode(post_breadcrumb_schema($post), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
	<?php endif; ?>

	<?php if ($faq_schema !== []): ?>
	<script type="application/ld+json"><?php
		$entities = [];
		foreach ($faq_schema as $item) {
			if (empty($item['question']) || empty($item['answer'])) {
				continue;
			}
			$entities[] = [
				'@type' => 'Question',
				'name' => $item['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text' => $item['answer']
				]
			];
		}
		if ($entities !== []) {
			echo json_encode([
				'@context' => 'https://schema.org',
				'@type' => 'FAQPage',
				'mainEntity' => $entities
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
	?></script>
	<?php endif; ?>

	<!-- Bootstrap JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>