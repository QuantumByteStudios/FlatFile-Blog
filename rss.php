<?php

/**
 * FlatFile Blog - Enhanced RSS Feed
 * Generates RSS 2.0 feed with comprehensive metadata
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
    die('System error. Please try again later.');
}

// Get latest published posts
$posts = get_posts(1, 20, 'published'); // Get first 20 published posts

// Set RSS headers with caching
header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: public, max-age=3600'); // 1 hour cache
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime(CONTENT_DIR . 'index.json')) . ' GMT');

// Generate RSS XML
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title><?php echo htmlspecialchars(SITE_TITLE); ?></title>
        <link><?php echo BASE_URL; ?></link>
        <description>A fast, lightweight flat-file blog system built with PHP and Bootstrap</description>
        <language>en-us</language>
        <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
        <generator>FlatFile Blog v1.0</generator>
        <managingEditor><?php echo htmlspecialchars($posts[0]['author'] ?? 'admin'); ?>@<?php echo parse_url(BASE_URL, PHP_URL_HOST); ?></managingEditor>
        <webMaster><?php echo htmlspecialchars($posts[0]['author'] ?? 'admin'); ?>@<?php echo parse_url(BASE_URL, PHP_URL_HOST); ?></webMaster>
        <ttl>60</ttl>
        <atom:link href="<?php echo BASE_URL; ?>rss" rel="self" type="application/rss+xml" />

        <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
                <item>
                    <title><?php echo htmlspecialchars($post['title']); ?></title>
                    <link><?php echo BASE_URL; ?><?php echo urlencode($post['slug']); ?></link>
                    <description>
                        <![CDATA[<?php echo htmlspecialchars($post['excerpt'] ?: substr(strip_tags($post['content'] ?? ''), 0, 200) . '...'); ?>]]>
                    </description>
                    <pubDate><?php echo date('r', strtotime($post['date'])); ?></pubDate>
                    <guid isPermaLink="true"><?php echo BASE_URL; ?><?php echo urlencode($post['slug']); ?></guid>
                    <author><?php echo htmlspecialchars($post['author']); ?>@<?php echo parse_url(BASE_URL, PHP_URL_HOST); ?></author>
                    <dc:creator><?php echo htmlspecialchars($post['author']); ?></dc:creator>
                    <?php if (!empty($post['tags'])): ?>
                        <?php foreach ($post['tags'] as $tag): ?>
                            <category><?php echo htmlspecialchars($tag); ?></category>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($post['meta']['image'])): ?>
                        <enclosure url="<?php echo htmlspecialchars($post['meta']['image']); ?>" type="image/jpeg" />
                    <?php endif; ?>
                </item>
            <?php endforeach; ?>
        <?php endif; ?>
    </channel>
</rss>