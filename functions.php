<?php
declare(strict_types=1);

/**
 * FlatFile Blog Functions
 * Core functionality for the blog system
 */

// Direct access protection is handled by .htaccess file

// Load configuration (robust path resolution for nested routes like /admin/)
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    if (basename($_SERVER['PHP_SELF']) === 'install.php') {
        return; // Allow install.php to run without config
    }
    die('Configuration file not found. Please run install.php first.');
}

try {
    require_once $configPath;
} catch (Exception $e) {
    error_log('Error loading config.php: ' . $e->getMessage());
    die('Configuration error. Please check your installation.');
}

/**
 * Load blog settings from JSON file
 */
function load_settings()
{
    $settings_file = CONTENT_DIR . 'settings.json';

    if (!file_exists($settings_file)) {
        return [
            'site_title' => 'My FlatFile Blog',
            'site_description' => 'A simple, fast, and secure flat-file blog system.',
            'posts_per_page' => 10,
            'timezone' => 'UTC',
            'theme' => 'default',
            'allow_comments' => true,
            'moderate_comments' => false,
            'social_links' => [],
            'analytics_code' => '',
            'maintenance_mode' => false
        ];
    }

    $settings = json_decode(file_get_contents($settings_file), true);
    return $settings ?: [];
}

/**
 * Save blog settings to JSON file
 */
function save_settings($settings)
{
    $settings_file = CONTENT_DIR . 'settings.json';

    // Ensure content directory exists
    if (!file_exists(CONTENT_DIR)) {
        mkdir(CONTENT_DIR, 0755, true);
    }

    return file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
}

/**
 * Get all blog posts
 */
function get_posts($page = 1, $per_page = 10, $status = 'published', $category = '', $tag = '')
{
    $posts_dir = CONTENT_DIR . 'posts/';

    if (!file_exists($posts_dir)) {
        return [];
    }

    clearstatcache(true, $posts_dir);
    $files = glob($posts_dir . '*.json');
    $all_posts = [];
    $current_time = time();
    $category_filter = trim($category);
    $tag_filter = trim($tag);

    foreach ($files as $file) {
        clearstatcache(true, $file);
        $post_data = json_decode(file_get_contents($file), true);
        if (!$post_data) {
            continue;
        }

        if ($status !== 'all' && ($post_data['status'] ?? '') !== $status) {
            continue;
        }

        if ($status === 'published' && ($post_data['status'] ?? '') === 'published') {
            $post_date = isset($post_data['date']) ? strtotime($post_data['date']) : 0;
            if ($post_date > $current_time) {
                continue;
            }
        }

        if ($category_filter !== '') {
            $cats = array_map('mb_strtolower', $post_data['categories'] ?? []);
            if (!in_array(mb_strtolower($category_filter), $cats, true)) {
                continue;
            }
        }

        if ($tag_filter !== '') {
            $tags = array_map('mb_strtolower', $post_data['tags'] ?? []);
            if (!in_array(mb_strtolower($tag_filter), $tags, true)) {
                continue;
            }
        }

        $all_posts[] = $post_data;
    }

    usort($all_posts, function ($a, $b) {
        return strtotime($b['date'] ?? '1970-01-01') - strtotime($a['date'] ?? '1970-01-01');
    });

    $offset = ($page - 1) * $per_page;
    return array_slice($all_posts, $offset, $per_page);
}

/**
 * Count posts with optional category/tag filters
 */
function count_posts_filtered($status = 'published', $category = '', $tag = '')
{
    $posts_dir = CONTENT_DIR . 'posts/';
    if (!file_exists($posts_dir)) {
        return 0;
    }

    $count = 0;
    $current_time = time();
    $category_filter = trim($category);
    $tag_filter = trim($tag);

    foreach (glob($posts_dir . '*.json') as $file) {
        $post_data = json_decode(file_get_contents($file), true);
        if (!$post_data || ($post_data['status'] ?? '') !== $status) {
            continue;
        }
        if ($status === 'published') {
            $post_date = isset($post_data['date']) ? strtotime($post_data['date']) : 0;
            if ($post_date > $current_time) {
                continue;
            }
        }
        if ($category_filter !== '') {
            $cats = array_map('mb_strtolower', $post_data['categories'] ?? []);
            if (!in_array(mb_strtolower($category_filter), $cats, true)) {
                continue;
            }
        }
        if ($tag_filter !== '') {
            $tags = array_map('mb_strtolower', $post_data['tags'] ?? []);
            if (!in_array(mb_strtolower($tag_filter), $tags, true)) {
                continue;
            }
        }
        $count++;
    }

    return $count;
}

/**
 * Collect unique categories from all posts
 */
function collect_all_categories(): array
{
    $categories = [];
    foreach (all_posts() as $post) {
        foreach ($post['categories'] ?? [] as $cat) {
            $cat = trim((string) $cat);
            if ($cat !== '') {
                $categories[$cat] = true;
            }
        }
    }
    $list = array_keys($categories);
    natcasesort($list);
    return array_values($list);
}

/**
 * Plain text from post body for word count / read time
 */
function post_plain_text(array $post): string
{
    $content_type = $post['content_type'] ?? (isset($post['content_markdown']) ? 'markdown' : 'html');
    if ($content_type === 'html') {
        $raw = $post['content_html'] ?? $post['content'] ?? '';
    } else {
        $raw = $post['content_markdown'] ?? $post['content'] ?? '';
    }
    return trim(strip_tags((string) $raw));
}

/**
 * Word count for a post
 */
function post_word_count(array $post): int
{
    $text = post_plain_text($post);
    if ($text === '') {
        return 0;
    }
    return str_word_count($text);
}

/**
 * Estimated read time in minutes (200 wpm)
 */
function post_read_time_minutes(array $post): int
{
    $words = post_word_count($post);
    return max(1, (int) ceil($words / 200));
}

/**
 * SEO field helpers with fallbacks
 */
function post_meta_title(array $post): string
{
    $seo = $post['seo'] ?? [];
    if (!empty($seo['meta_title'])) {
        return (string) $seo['meta_title'];
    }
    return (string) ($post['title'] ?? '');
}

function post_meta_description(array $post, string $html_fallback = ''): string
{
    $seo = $post['seo'] ?? [];
    if (!empty($seo['meta_description'])) {
        return (string) $seo['meta_description'];
    }
    if (!empty($post['excerpt'])) {
        return (string) $post['excerpt'];
    }
    if ($html_fallback !== '') {
        return substr(strip_tags($html_fallback), 0, 160);
    }
    return '';
}

function post_canonical_url(array $post): string
{
    $seo = $post['seo'] ?? [];
    if (!empty($seo['canonical_url'])) {
        return (string) $seo['canonical_url'];
    }
    return rtrim(BASE_URL, '/') . '/' . rawurlencode($post['slug'] ?? '');
}

function post_robots_index(array $post): bool
{
    $robots = $post['seo']['robots'] ?? 'index';
    return $robots !== 'noindex';
}

function post_og_title(array $post): string
{
    $og = $post['og'] ?? [];
    if (!empty($og['title'])) {
        return (string) $og['title'];
    }
    return post_meta_title($post);
}

function post_og_description(array $post, string $html_fallback = ''): string
{
    $og = $post['og'] ?? [];
    if (!empty($og['description'])) {
        return (string) $og['description'];
    }
    return post_meta_description($post, $html_fallback);
}

/**
 * Featured / thumbnail image URL for a post.
 */
function post_featured_image_url(array $post): string
{
    return trim((string) ($post['meta']['image'] ?? ''));
}

/**
 * OG image: dedicated OG upload if set, otherwise featured thumbnail.
 */
function post_og_image(array $post): string
{
    $og = $post['og'] ?? [];
    $dedicated = trim((string) ($og['image'] ?? ''));
    $featured = post_featured_image_url($post);

    if ($dedicated !== '' && !empty($og['image_custom'])) {
        return $dedicated;
    }

    if ($dedicated !== '' && str_contains($dedicated, '/uploads/og/')) {
        return $dedicated;
    }

    if ($featured !== '') {
        return $featured;
    }

    return $dedicated;
}

function post_use_twitter_card(array $post): bool
{
    return !isset($post['og']['twitter_card']) || $post['og']['twitter_card'] !== false;
}

function post_sitemap_priority(array $post): float
{
    $p = $post['sitemap_priority'] ?? 0.9;
    $p = is_numeric($p) ? (float) $p : 0.9;
    return max(0.1, min(1.0, $p));
}

/**
 * Related posts by slug list (published only)
 */
function get_related_posts(array $post, int $limit = 3): array
{
    $slugs = $post['related_posts'] ?? [];
    if (!is_array($slugs) || $slugs === []) {
        return [];
    }
    $current = $post['slug'] ?? '';
    $related = [];
    foreach ($slugs as $slug) {
        $slug = trim((string) $slug);
        if ($slug === '' || $slug === $current) {
            continue;
        }
        $p = get_post($slug);
        if ($p && ($p['status'] ?? '') === 'published') {
            $related[] = $p;
        }
        if (count($related) >= $limit) {
            break;
        }
    }
    return $related;
}

/**
 * Build table of contents HTML from H2/H3 and inject heading ids
 */
function build_table_of_contents(string $html): array
{
    $used_ids = [];
    $items = [];

    $html = preg_replace_callback(
        '/<h([23])(\s[^>]*)?>(.*?)<\/h\1>/is',
        function ($m) use (&$used_ids, &$items) {
            $level = (int) $m[1];
            $inner = $m[3];
            $text = trim(strip_tags($inner));
            if ($text === '') {
                return $m[0];
            }
            $base = preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower($text));
            $base = trim($base, '-') ?: 'section';
            $id = $base;
            $n = 2;
            while (isset($used_ids[$id])) {
                $id = $base . '-' . $n;
                $n++;
            }
            $used_ids[$id] = true;
            $items[] = ['level' => $level, 'text' => $text, 'id' => $id];
            $attrs = $m[2] ?? '';
            if (stripos($attrs, 'id=') === false) {
                $attrs .= ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"';
            }
            return '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>';
        },
        $html
    );

    if ($items === []) {
        return ['html' => $html, 'toc' => ''];
    }

    $toc = '<nav class="post-toc mb-4 p-3 border rounded" aria-label="Table of contents"><h2 class="h6 mb-2">Table of contents</h2><ol class="mb-0">';
    foreach ($items as $item) {
        $indent = $item['level'] === 3 ? ' class="ms-3"' : '';
        $toc .= '<li' . $indent . '><a href="#' . htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
    $toc .= '</ol></nav>';

    return ['html' => $html, 'toc' => $toc];
}

/**
 * Breadcrumb HTML for a post
 */
function render_post_breadcrumbs(array $post): string
{
    $home = rtrim(BASE_URL, '/') . '/';
    $blogs = rtrim(BASE_URL, '/') . '/blogs';
    $title = htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8');
    $html = '<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb">';
    $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($home, ENT_QUOTES, 'UTF-8') . '">Home</a></li>';
    $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($blogs, ENT_QUOTES, 'UTF-8') . '">Blog</a></li>';
    if (!empty($post['categories'][0])) {
        $cat = $post['categories'][0];
        $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($home . '?category=' . urlencode($cat), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
    $html .= '<li class="breadcrumb-item active" aria-current="page">' . $title . '</li>';
    $html .= '</ol></nav>';
    return $html;
}

/**
 * JSON-LD breadcrumb schema
 */
function post_breadcrumb_schema(array $post): array
{
    $items = [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Home',
            'item' => rtrim(BASE_URL, '/') . '/'
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => 'Blog',
            'item' => rtrim(BASE_URL, '/') . '/blogs'
        ]
    ];
    $pos = 3;
    if (!empty($post['categories'][0])) {
        $cat = $post['categories'][0];
        $items[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => $cat,
            'item' => rtrim(BASE_URL, '/') . '/?category=' . rawurlencode($cat)
        ];
    }
    $items[] = [
        '@type' => 'ListItem',
        'position' => $pos,
        'name' => $post['title'] ?? '',
        'item' => post_canonical_url($post)
    ];
    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items
    ];
}

/**
 * Site favicon URL from settings
 */
function site_favicon_url(): string
{
    $settings = load_settings();
    return (string) ($settings['favicon_url'] ?? '');
}

/**
 * Whether updater should skip index.php and post.php
 */
function should_preserve_custom_templates(): bool
{
    $root = dirname(__FILE__);
    if (file_exists($root . '/.preserve-custom-templates')) {
        return true;
    }
    if (defined('INTERFACE_MODE') && (string) constant('INTERFACE_MODE') === 'custom') {
        return true;
    }
    $settings = load_settings();
    if (($settings['interface_mode'] ?? '') === 'custom') {
        return true;
    }
    return !empty($settings['preserve_custom_templates']);
}

/**
 * Mark project to preserve custom index.php / post.php on updates
 */
function enable_custom_template_preservation(): bool
{
    $marker = dirname(__FILE__) . '/.preserve-custom-templates';
    return (bool) file_put_contents($marker, date('c') . "\n");
}

/**
 * Get a single post by slug
 */
function get_post($slug)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return null;
    }

    // Clear stat cache before reading to ensure fresh data
    clearstatcache(true, $post_file);
    $post_data = json_decode(file_get_contents($post_file), true);
    return $post_data ?: null;
}

/**
 * Count total posts
 */
function count_posts($status = 'published')
{
    $posts_dir = CONTENT_DIR . 'posts/';

    if (!file_exists($posts_dir)) {
        return 0;
    }

    $files = glob($posts_dir . '*.json');
    $count = 0;
    $current_time = time();

    foreach ($files as $file) {
        $post_data = json_decode(file_get_contents($file), true);
        if (!$post_data) {
            continue;
        }

        if ($post_data['status'] !== $status) {
            continue;
        }

        // For published posts, exclude future-dated posts (scheduled)
        if ($status === 'published') {
            $post_date = isset($post_data['date']) ? strtotime($post_data['date']) : 0;
            // If post date is in the future, don't count it
            if ($post_date > $current_time) {
                continue;
            }
        }

        $count++;
    }

    return $count;
}

/**
 * Get pagination information
 */
function get_pagination_info($total_posts, $per_page, $current_page)
{
    $total_pages = ceil($total_posts / $per_page);

    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'prev_page' => $current_page > 1 ? $current_page - 1 : null,
        'next_page' => $current_page < $total_pages ? $current_page + 1 : null,
        'total_posts' => $total_posts,
        'per_page' => $per_page
    ];
}

/**
 * Create a new post
 */
function create_post($title, $content, $excerpt = '', $author = '', $status = 'draft', $tags = [])
{
    $slug = create_slug($title);
    $date = date('Y-m-d H:i:s');

    $post_data = [
        'title' => $title,
        'content' => $content,
        'excerpt' => $excerpt ?: substr(strip_tags($content), 0, 200) . '...',
        'author' => $author ?: (defined('ADMIN_USERNAME') ? constant('ADMIN_USERNAME') : 'Admin'),
        'date' => $date,
        'updated' => $date,
        'slug' => $slug,
        'status' => $status,
        'tags' => $tags,
        'views' => 0,
        'comments' => []
    ];

    $posts_dir = CONTENT_DIR . 'posts/';
    if (!file_exists($posts_dir)) {
        mkdir($posts_dir, 0755, true);
    }

    $post_file = $posts_dir . $slug . '.json';
    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Update an existing post
 */
function update_post($slug, $title, $content, $excerpt = '', $author = '', $status = 'draft', $tags = [])
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $post_data = json_decode(file_get_contents($post_file), true);
    if (!$post_data) {
        return false;
    }

    $post_data['title'] = $title;
    $post_data['content'] = $content;
    $post_data['excerpt'] = $excerpt ?: substr(strip_tags($content), 0, 200) . '...';
    $post_data['author'] = $author ?: $post_data['author'];
    $post_data['updated'] = date('Y-m-d H:i:s');
    $post_data['status'] = $status;
    $post_data['tags'] = $tags;

    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Delete a post
 */
function delete_post($slug)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $ok = unlink($post_file);
    if ($ok) {
        // Clear PHP's stat cache
        clearstatcache(true, $post_file);
        clearstatcache(true, dirname($post_file));
        // Rebuild index after deletion
        rebuild_index();
    }
    return $ok;
}

/**
 * Create URL-friendly slug from title
 */
function create_slug($title)
{
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');

    // Ensure uniqueness
    $original_slug = $slug;
    $counter = 1;

    while (file_exists(CONTENT_DIR . 'posts/' . $slug . '.json')) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }

    return $slug;
}


/**
 * Return all posts regardless of status (newest first)
 */
function all_posts()
{
    $posts_dir = CONTENT_DIR . 'posts/';
    $all = [];
    if (!file_exists($posts_dir)) {
        return $all;
    }
    // Clear stat cache to ensure fresh file listings
    clearstatcache(true, $posts_dir);
    $files = glob($posts_dir . '*.json');
    foreach ($files as $file) {
        // Clear stat cache for each file before reading
        clearstatcache(true, $file);
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $all[] = $data;
        }
    }
    usort($all, function ($a, $b) {
        return strtotime($b['date'] ?? '1970-01-01') - strtotime($a['date'] ?? '1970-01-01');
    });
    return $all;
}

/**
 * Get posts filtered by status (helper for admin/tools)
 */
function get_posts_by_status($status)
{
    $all = all_posts();
    $current_time = time();
    
    return array_values(array_filter($all, function ($p) use ($status, $current_time) {
        if (!isset($p['status']) || $p['status'] !== $status) {
            return false;
        }
        
        // For published posts, exclude future-dated posts (scheduled)
        if ($status === 'published') {
            $post_date = isset($p['date']) ? strtotime($p['date']) : 0;
            // If post date is in the future, exclude it
            if ($post_date > $current_time) {
                return false;
            }
        }
        
        return true;
    }));
}

/**
 * Rebuild the content index file from post JSON files
 */
function rebuild_index()
{
    $posts_dir = CONTENT_DIR . 'posts/';
    if (!file_exists($posts_dir)) {
        if (!mkdir($posts_dir, 0755, true)) {
            return false;
        }
    }

    $files = glob($posts_dir . '*.json');
    $index = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data) {
            continue;
        }
        $index[] = [
            'slug' => $data['slug'] ?? pathinfo($file, PATHINFO_FILENAME),
            'title' => $data['title'] ?? '',
            'date' => $data['date'] ?? '',
            'updated' => $data['updated'] ?? ($data['date'] ?? ''),
            'status' => $data['status'] ?? 'draft',
            'excerpt' => $data['excerpt'] ?? generate_excerpt($data['content'] ?? ($data['content_markdown'] ?? $data['content_html'] ?? '')),
            'tags' => $data['tags'] ?? [],
            'categories' => $data['categories'] ?? [],
            'author' => $data['author'] ?? (defined('ADMIN_USERNAME') ? constant('ADMIN_USERNAME') : 'Admin'),
            'meta' => $data['meta'] ?? [],
            'word_count' => $data['word_count'] ?? post_word_count($data),
            'read_time' => $data['read_time'] ?? post_read_time_minutes($data),
            'sitemap_priority' => $data['sitemap_priority'] ?? 0.9
        ];
    }
    // Sort newest first
    usort($index, function ($a, $b) {
        return strtotime($b['date'] ?? '1970-01-01') - strtotime($a['date'] ?? '1970-01-01');
    });
    return (bool) file_put_contents(CONTENT_DIR . 'index.json', json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Aliases for compatibility with admin code
 */
function load_post($slug)
{
    return get_post($slug);
}

function save_post(array $post_data)
{
    if (empty($post_data['slug'])) {
        return false;
    }
    $file = CONTENT_DIR . 'posts/' . $post_data['slug'] . '.json';
    if (!file_exists(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    $ok = (bool) file_put_contents($file, json_encode($post_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($ok) {
        // Clear PHP's stat cache to ensure fresh file reads
        clearstatcache(true, $file);
        // Clear directory stat cache
        clearstatcache(true, dirname($file));
        // Rebuild index
        rebuild_index();
    }
    return $ok;
}

function slugify($text)
{
    return create_slug($text);
}

/**
 * Increment post views
 */
function increment_post_views($slug)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $post_data = json_decode(file_get_contents($post_file), true);
    if (!$post_data) {
        return false;
    }

    $post_data['views'] = ($post_data['views'] ?? 0) + 1;

    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Add comment to post
 */
function add_comment($slug, $name, $email, $comment)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $post_data = json_decode(file_get_contents($post_file), true);
    if (!$post_data) {
        return false;
    }

    $comment_data = [
        'id' => uniqid(),
        'name' => $name,
        'email' => $email,
        'comment' => $comment,
        'date' => date('Y-m-d H:i:s'),
        'approved' => false
    ];

    $post_data['comments'][] = $comment_data;

    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Approve comment
 */
function approve_comment($slug, $comment_id)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $post_data = json_decode(file_get_contents($post_file), true);
    if (!$post_data) {
        return false;
    }

    foreach ($post_data['comments'] as &$comment) {
        if ($comment['id'] === $comment_id) {
            $comment['approved'] = true;
            break;
        }
    }

    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Delete comment
 */
function delete_comment($slug, $comment_id)
{
    $post_file = CONTENT_DIR . 'posts/' . $slug . '.json';

    if (!file_exists($post_file)) {
        return false;
    }

    $post_data = json_decode(file_get_contents($post_file), true);
    if (!$post_data) {
        return false;
    }

    $post_data['comments'] = array_filter($post_data['comments'], function ($comment) use ($comment_id) {
        return $comment['id'] !== $comment_id;
    });

    return file_put_contents($post_file, json_encode($post_data, JSON_PRETTY_PRINT));
}

/**
 * Check if user is logged in
 */
function is_logged_in()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Login user
 */
function login_user($username, $password)
{
    if (!defined('ADMIN_USERNAME') || !defined('ADMIN_PASSWORD_HASH')) {
        return false;
    }
    $admin_username = constant('ADMIN_USERNAME');
    $admin_password_hash = constant('ADMIN_PASSWORD_HASH');
    if ($username === $admin_username && password_verify($password, $admin_password_hash)) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        return true;
    }
    return false;
}

/**
 * Logout user
 */
function logout_user()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
}

/**
 * Check session timeout
 */
function check_session_timeout()
{
    if (!is_logged_in()) {
        return false;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $timeout = defined('SESSION_TIMEOUT') ? constant('SESSION_TIMEOUT') : 3600; // Default 1 hour
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout) {
        logout_user();
        return false;
    }

    return true;
}

/**
 * Generate CSRF token
 */
function generate_csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input
 */
function sanitize_input($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validate_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Log error
 */
function log_error($message, $context = [])
{
    $logs_dir = defined('LOGS_DIR') ? constant('LOGS_DIR') : __DIR__ . '/logs/';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'ERROR',
        'message' => $message,
        'context' => $context,
        'file' => $_SERVER['PHP_SELF'] ?? 'unknown',
        'line' => debug_backtrace()[0]['line'] ?? 'unknown'
    ];

    $log_file = $logs_dir . 'error.log';
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Log info
 */
function log_info($message, $context = [])
{
    $logs_dir = defined('LOGS_DIR') ? constant('LOGS_DIR') : __DIR__ . '/logs/';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'INFO',
        'message' => $message,
        'context' => $context
    ];

    $log_file = $logs_dir . 'app.log';
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Get system health
 */
function get_system_health()
{
    $health = [
        'status' => 'healthy',
        'checks' => []
    ];

    // Check if content directory is writable
    $health['checks']['content_writable'] = is_writable(CONTENT_DIR);

    // Check if logs directory is writable
    $logs_dir = defined('LOGS_DIR') ? constant('LOGS_DIR') : __DIR__ . '/logs/';
    $health['checks']['logs_writable'] = is_writable($logs_dir);

    // Check if uploads directory is writable
    $uploads_dir = defined('UPLOADS_DIR') ? constant('UPLOADS_DIR') : __DIR__ . '/uploads/';
    $health['checks']['uploads_writable'] = is_writable($uploads_dir);

    // Check PHP version
    $health['checks']['php_version'] = version_compare(PHP_VERSION, '7.4.0', '>=');

    // Check required extensions
    $required_extensions = ['json', 'fileinfo', 'mbstring'];
    $health['checks']['extensions'] = [];
    foreach ($required_extensions as $ext) {
        $health['checks']['extensions'][$ext] = extension_loaded($ext);
    }

    // Overall status
    $all_checks = array_merge(
        [$health['checks']['content_writable'], $health['checks']['logs_writable'], $health['checks']['uploads_writable'], $health['checks']['php_version']],
        array_values($health['checks']['extensions'])
    );

    if (in_array(false, $all_checks)) {
        $health['status'] = 'warning';
    }

    return $health;
}

/**
 * Format date for display
 */
function format_date($date, $format = 'F j, Y')
{
    return date($format, strtotime($date));
}

/**
 * Generate excerpt from content
 */
function generate_excerpt($content, $length = 200)
{
    $excerpt = strip_tags($content);
    if (strlen($excerpt) > $length) {
        $excerpt = substr($excerpt, 0, $length) . '...';
    }
    return $excerpt;
}

/**
 * Get post URL
 */
function get_post_url($slug)
{
    return BASE_URL . 'post.php?slug=' . urlencode($slug);
}

/**
 * Get admin URL
 */
function get_admin_url($page = '')
{
    return BASE_URL . 'admin/' . $page;
}

/**
 * Redirect with message
 */
function redirect_with_message($url, $message, $type = 'info')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit;
}

/**
 * Get and clear flash message
 */
function get_flash_message()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }

    return null;
}

/**
 * Check if maintenance mode is enabled
 */
function is_maintenance_mode()
{
    $settings = load_settings();
    return isset($settings['maintenance_mode']) && $settings['maintenance_mode'] === true;
}


/**
 * Clean old log files
 */
function clean_old_logs($days = 30)
{
    $logs_dir = defined('LOGS_DIR') ? constant('LOGS_DIR') : __DIR__ . '/logs/';
    if (!file_exists($logs_dir)) {
        return;
    }

    $files = glob($logs_dir . '*.log');
    $cutoff = time() - ($days * 24 * 60 * 60);

    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
}

/**
 * Backup content
 */
function backup_content()
{
    $logs_dir = defined('LOGS_DIR') ? constant('LOGS_DIR') : __DIR__ . '/logs/';
    $backup_dir = $logs_dir . 'backups/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $backup_file = $backup_dir . 'content_backup_' . date('Y-m-d_H-i-s') . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($backup_file, ZipArchive::CREATE) === TRUE) {
        $zip->addFile(CONTENT_DIR . 'settings.json', 'settings.json');
        $zip->addFile(CONTENT_DIR . 'index.json', 'index.json');

        // Add all posts
        $posts_dir = CONTENT_DIR . 'posts/';
        if (file_exists($posts_dir)) {
            $files = glob($posts_dir . '*.json');
            foreach ($files as $file) {
                $zip->addFile($file, 'posts/' . basename($file));
            }
        }

        $zip->close();
        return $backup_file;
    }

    return false;
}


/**
 * Initialize blog
 */
function init_blog()
{
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Set timezone
    if (defined('TIMEZONE')) {
        date_default_timezone_set(constant('TIMEZONE'));
    }

    // Check maintenance mode
    if (is_maintenance_mode() && !is_logged_in()) {
        http_response_code(503);
        // Maintenance mode - show simple message
        die('Site is currently under maintenance. Please check back later.');
    }

    // Clean old logs periodically
    if (rand(1, 100) === 1) {
        clean_old_logs();
    }
}

// Initialize blog
init_blog();
