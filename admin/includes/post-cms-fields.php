<?php
declare(strict_types=1);
/**
 * @deprecated Use post-cms-seo.php (main column) + post-cms-advanced.php (sidebar).
 */
$post = $post ?? [];
$current_slug = $current_slug ?? ($post['slug'] ?? '');
include __DIR__ . '/post-cms-seo.php';
include __DIR__ . '/post-cms-advanced.php';
