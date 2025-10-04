<?php

/**
 * Rebuild Index Tool
 * Regenerates the content/index.json file from all post files
 * 
 * Usage: php tools/rebuild_index.php
 */

require_once __DIR__ . '/../../functions.php';

echo "Rebuilding index...\n";

$start_time = microtime(true);
$result = rebuild_index();
$end_time = microtime(true);

if ($result) {
    $posts = all_posts();
    $duration = round(($end_time - $start_time) * 1000, 2);

    echo "✅ Index rebuilt successfully!\n";
    echo "📊 Statistics:\n";
    echo "   - Total posts: " . count($posts) . "\n";
    echo "   - Processing time: {$duration}ms\n";
    echo "   - Index file: " . CONTENT_DIR . "index.json\n";

    // Show breakdown by status
    $status_counts = [];
    foreach ($posts as $post) {
        $status = $post['status'] ?? 'unknown';
        $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
    }

    echo "\n📈 Posts by status:\n";
    foreach ($status_counts as $status => $count) {
        echo "   - {$status}: {$count}\n";
    }
} else {
    echo "❌ Failed to rebuild index!\n";
    echo "Check file permissions and directory structure.\n";
    exit(1);
}

echo "\n🎉 Index rebuild complete!\n";
