<?php

/**
 * FlatFile Blog - Maintenance Tools
 * System maintenance and cleanup utilities
 */

require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/../../libs/AdminLogger.php';

// Check if user is logged in (basic auth for maintenance)
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied. Admin login required.');
}

class MaintenanceTools
{
    /**
     * Clean up old log files
     */
    public static function cleanLogs($days = 90)
    {
        AdminLogger::cleanOldLogs($days);
        return ['success' => true, 'message' => "Cleaned logs older than {$days} days"];
    }

    /**
     * Rebuild search index
     */
    public static function rebuildIndex()
    {
        $result = rebuild_index();
        return [
            'success' => $result,
            'message' => $result ? 'Index rebuilt successfully' : 'Failed to rebuild index'
        ];
    }

    /**
     * Clean up orphaned files
     */
    public static function cleanOrphanedFiles()
    {
        $cleaned = 0;
        $errors = [];

        // Clean up orphaned uploads
        if (file_exists(UPLOADS_DIR)) {
            $upload_files = glob(UPLOADS_DIR . '**/*', GLOB_BRACE);
            $posts = all_posts();
            $used_images = [];

            // Collect all used images from posts
            foreach ($posts as $post) {
                if (!empty($post['meta']['image'])) {
                    $used_images[] = basename($post['meta']['image']);
                }
            }

            // Remove unused files
            foreach ($upload_files as $file) {
                if (is_file($file)) {
                    $filename = basename($file);
                    if (!in_array($filename, $used_images)) {
                        if (unlink($file)) {
                            $cleaned++;
                        } else {
                            $errors[] = "Failed to delete: {$file}";
                        }
                    }
                }
            }
        }

        return [
            'success' => true,
            'message' => "Cleaned {$cleaned} orphaned files",
            'errors' => $errors
        ];
    }

    /**
     * Optimize images
     */
    public static function optimizeImages()
    {
        $optimized = 0;
        $errors = [];

        if (file_exists(UPLOADS_DIR)) {
            $image_files = glob(UPLOADS_DIR . '**/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);

            foreach ($image_files as $file) {
                if (is_file($file)) {
                    $temp_file = $file . '.tmp';
                    if (self::optimizeImage($file, $temp_file)) {
                        if (rename($temp_file, $file)) {
                            $optimized++;
                        } else {
                            $errors[] = "Failed to replace: {$file}";
                        }
                    } else {
                        if (file_exists($temp_file)) {
                            unlink($temp_file);
                        }
                    }
                }
            }
        }

        return [
            'success' => true,
            'message' => "Optimized {$optimized} images",
            'errors' => $errors
        ];
    }

    /**
     * Get system statistics
     */
    public static function getSystemStats()
    {
        $stats = [
            'posts' => count(get_posts_by_status('published')),
            'drafts' => count(get_posts_by_status('draft')),
            'total_posts' => count(all_posts()),
            'disk_usage' => self::getDirectorySize(__DIR__ . '/../'),
            'uploads_size' => file_exists(UPLOADS_DIR) ? self::getDirectorySize(UPLOADS_DIR) : 0,
            'content_size' => self::getDirectorySize(CONTENT_DIR),
            'log_size' => file_exists(__DIR__ . '/../logs/') ? self::getDirectorySize(__DIR__ . '/../logs/') : 0,
            'backup_size' => file_exists(__DIR__ . '/../backups/') ? self::getDirectorySize(__DIR__ . '/../backups/') : 0
        ];

        return $stats;
    }

    /**
     * Get directory size
     */
    private static function getDirectorySize($dir)
    {
        $size = 0;
        if (is_dir($dir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }
        return $size;
    }

    /**
     * Optimize single image
     */
    private static function optimizeImage($source, $destination)
    {
        $info = getimagesize($source);
        if (!$info) return false;

        $width = $info[0];
        $height = $info[1];
        $type = $info[2];

        // Create source image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($source);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($source);
                break;
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($source);
                break;
            default:
                return false;
        }

        if (!$image) return false;

        // Save optimized image
        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($image, $destination, 85);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($image, $destination, 8);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($image, $destination);
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($image, $destination, 85);
                break;
        }

        imagedestroy($image);
        return $result;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];
    $result = ['success' => false, 'message' => 'Unknown action'];

    switch ($action) {
        case 'clean_logs':
            $days = intval($_POST['days'] ?? 90);
            $result = MaintenanceTools::cleanLogs($days);
            break;

        case 'rebuild_index':
            $result = MaintenanceTools::rebuildIndex();
            break;

        case 'clean_orphaned':
            $result = MaintenanceTools::cleanOrphanedFiles();
            break;

        case 'optimize_images':
            $result = MaintenanceTools::optimizeImages();
            break;

        case 'get_stats':
            $result = ['success' => true, 'data' => MaintenanceTools::getSystemStats()];
            break;
    }

    echo json_encode($result);
    exit;
}

// Get system statistics
$stats = MaintenanceTools::getSystemStats();
$recent_logs = AdminLogger::getRecentLogs(10);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Tools - <?php echo SITE_TITLE; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/main.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1>Maintenance Tools</h1>
                <p class="text-muted">System maintenance and cleanup utilities</p>

                <!-- System Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $stats['total_posts']; ?></h5>
                                <p class="card-text">Total Posts</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo round($stats['disk_usage'] / 1024 / 1024, 2); ?> MB</h5>
                                <p class="card-text">Disk Usage</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo round($stats['uploads_size'] / 1024 / 1024, 2); ?> MB</h5>
                                <p class="card-text">Uploads Size</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo round($stats['log_size'] / 1024, 2); ?> KB</h5>
                                <p class="card-text">Log Size</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Actions -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">System Maintenance</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary" onclick="runMaintenance('rebuild_index')">
                                        <i class="bi bi-arrow-clockwise"></i> Rebuild Index
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="runMaintenance('clean_orphaned')">
                                        <i class="bi bi-trash"></i> Clean Orphaned Files
                                    </button>
                                    <button class="bi bi-image"></i> Optimize Images
                                    </button>
                                    <button class="btn btn-outline-info" onclick="runMaintenance('clean_logs')">
                                        <i class="bi bi-file-earmark-text"></i> Clean Old Logs
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_logs)): ?>
                                    <p class="text-muted">No recent activity</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach (array_slice($recent_logs, 0, 5) as $log): ?>
                                            <div class="list-group-item px-0">
                                                <div class="d-flex justify-content-between">
                                                    <span class="fw-bold"><?php echo htmlspecialchars($log['action']); ?></span>
                                                    <small class="text-muted"><?php echo date('M j, H:i', strtotime($log['timestamp'])); ?></small>
                                                </div>
                                                <small class="text-muted"><?php echo htmlspecialchars($log['user']); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results -->
                <div id="maintenance-results" class="mt-4" style="display: none;">
                    <div class="alert alert-info">
                        <div id="result-message"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function runMaintenance(action) {
            const formData = new FormData();
            formData.append('action', action);

            if (action === 'clean_logs') {
                formData.append('days', 90);
            }

            fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('maintenance-results');
                    const messageDiv = document.getElementById('result-message');

                    if (data.success) {
                        messageDiv.innerHTML = '<i class="bi bi-check-circle"></i> ' + data.message;
                        resultsDiv.className = 'mt-4 alert alert-success';
                    } else {
                        messageDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + data.message;
                        resultsDiv.className = 'mt-4 alert alert-danger';
                    }

                    resultsDiv.style.display = 'block';

                    // Hide after 5 seconds
                    setTimeout(() => {
                        resultsDiv.style.display = 'none';
                    }, 5000);
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
    </script>
</body>

</html>