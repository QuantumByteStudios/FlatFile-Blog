<?php

/**
 * FlatFile Blog - Enhanced XML Sitemap
 * Generates comprehensive XML sitemap for search engines
 */

require_once 'functions.php';

// Get all published posts
$posts = get_posts_by_status('published');

// Set XML headers with caching
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600'); // 1 hour cache
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime(CONTENT_DIR . 'index.json')) . ' GMT');

// Generate XML sitemap
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <!-- Homepage -->
    <url>
        <loc><?php echo BASE_URL; ?></loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Search page -->
    <url>
        <loc><?php echo BASE_URL; ?>search</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Archive page -->
    <url>
        <loc><?php echo BASE_URL; ?>archive</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>

    <!-- RSS feed -->
    <url>
        <loc><?php echo BASE_URL; ?>rss</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.6</priority>
    </url>

    <!-- Individual posts -->
    <?php foreach ($posts as $post): ?>
        <url>
            <loc><?php echo BASE_URL; ?><?php echo urlencode($post['slug']); ?></loc>
            <lastmod><?php echo date('c', strtotime($post['updated'])); ?></lastmod>
            <changefreq>monthly</changefreq>
            <priority>0.9</priority>
            <?php if (!empty($post['meta']['image'])): ?>
                <image:image>
                    <image:loc><?php echo htmlspecialchars($post['meta']['image']); ?></image:loc>
                    <image:title><?php echo htmlspecialchars($post['title']); ?></image:title>
                    <image:caption><?php echo htmlspecialchars($post['excerpt'] ?: substr(strip_tags($post['content_markdown'] ?? ''), 0, 200)); ?></image:caption>
                </image:image>
            <?php endif; ?>
        </url>
    <?php endforeach; ?>
</urlset>