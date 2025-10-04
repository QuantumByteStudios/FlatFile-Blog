<?php

/**
 * Maintenance Tools
 * System maintenance and cleanup utilities
 */

require_once '../functions.php';
require_once __DIR__ . '/../libs/SecurityHardener.php';
require_once __DIR__ . '/../libs/AdminLogger.php';
require_once 'tools/backup.php';

// Initialize security system
SecurityHardener::init();

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'clean_logs':
                $days = intval($_POST['days'] ?? 90);
                AdminLogger::cleanOldLogs($days);
                $success_message = "Cleaned logs older than {$days} days";
                break;

            case 'rebuild_index':
                $result = rebuild_index();
                if ($result) {
                    $success_message = 'Index rebuilt successfully';
                } else {
                    $error_message = 'Failed to rebuild index';
                }
                break;

            case 'create_backup':
                $backup_result = BlogBackup::createBackup('manual');
                if ($backup_result['success']) {
                    $success_message = 'Backup created successfully';
                } else {
                    $error_message = 'Failed to create backup: ' . $backup_result['error'];
                }
                break;

            case 'clean_cache':
                // Clean cache files
                $cache_dir = __DIR__ . '/../cache/';
                if (is_dir($cache_dir)) {
                    $files = glob($cache_dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                }
                $success_message = 'Cache cleaned successfully';
                break;

            default:
                $error_message = 'Invalid action';
        }
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Get system statistics
$all_posts = all_posts();
$published_posts = get_posts_by_status('published');
$draft_posts = get_posts_by_status('draft');

// Get backup information
$backup_dir = __DIR__ . '/backups/';
$backups = [];
if (is_dir($backup_dir)) {
    $backup_files = glob($backup_dir . '*.zip');
    foreach ($backup_files as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file)
        ];
    }
    usort($backups, function ($a, $b) {
        return $b['date'] - $a['date'];
    });
}

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
    <link href="assets/css/admin.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 bg-dark min-vh-100 p-0">
                <div class="p-3">
                    <center>
                        <h5 class="text-light mb-4">
                            <a href="." class="text-light text-decoration-none">
                                Admin Panel
                            </a>
                        </h5>
                    </center>
                    <nav class="nav flex-column">
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/new-post">
                            <i class="bi bi-plus-circle"></i> New Post
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/settings">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                        <a class="nav-link text-light active" href="<?php echo BASE_URL; ?>admin/admin-tools">
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

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Maintenance Tools</h1>
                        <div class="text-muted">
                            System maintenance and cleanup utilities
                        </div>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- System Statistics -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-icon mb-3">
                                        <i class="bi bi-file-text text-dark"></i>
                                    </div>
                                    <h6 class="card-title text-muted mb-1">Total Posts</h6>
                                    <h3 class="mb-0 text-dark"><?php echo count($all_posts); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-icon mb-3">
                                        <i class="bi bi-check-circle text-dark"></i>
                                    </div>
                                    <h6 class="card-title text-muted mb-1">Published</h6>
                                    <h3 class="mb-0 text-dark"><?php echo count($published_posts); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-icon mb-3">
                                        <i class="bi bi-pencil text-dark"></i>
                                    </div>
                                    <h6 class="card-title text-muted mb-1">Drafts</h6>
                                    <h3 class="mb-0 text-dark"><?php echo count($draft_posts); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-icon mb-3">
                                        <i class="bi bi-archive text-dark"></i>
                                    </div>
                                    <h6 class="card-title text-muted mb-1">Backups</h6>
                                    <h3 class="mb-0 text-dark"><?php echo count($backups); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance Tools -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header" style="background: linear-gradient(135deg, #000 0%, #333 100%); color: white; border: none;">
                                    <h5 class="mb-0">
                                        <i class="bi bi-tools me-2"></i>System Maintenance
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <!-- Clean Logs -->
                                    <form method="POST" class="mb-3">
                                        <input type="hidden" name="action" value="clean_logs">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-trash text-warning me-2"></i>
                                            <strong>Clean Old Logs</strong>
                                        </div>
                                        <div class="row">
                                            <div class="col-8">
                                                <select name="days" class="form-select form-select-sm">
                                                    <option value="30">30 days</option>
                                                    <option value="60">60 days</option>
                                                    <option value="90" selected>90 days</option>
                                                    <option value="180">180 days</option>
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <button type="submit" class="btn btn-dark btn-sm w-100">
                                                    <i class="bi bi-trash"></i> Clean
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    <!-- Rebuild Index -->
                                    <form method="POST" class="mb-3">
                                        <input type="hidden" name="action" value="rebuild_index">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-arrow-clockwise text-info me-2"></i>
                                            <strong>Rebuild Search Index</strong>
                                        </div>
                                        <p class="text-muted small mb-2">Regenerate the content index for faster searches</p>
                                        <button type="submit" class="btn btn-dark btn-sm">
                                            <i class="bi bi-arrow-clockwise"></i> Rebuild Index
                                        </button>
                                    </form>

                                    <!-- Clean Cache -->
                                    <form method="POST" class="mb-3">
                                        <input type="hidden" name="action" value="clean_cache">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-broom text-secondary me-2"></i>
                                            <strong>Clean Cache</strong>
                                        </div>
                                        <p class="text-muted small mb-2">Clear all cached files and temporary data</p>
                                        <button type="submit" class="btn btn-dark btn-sm">
                                            <i class="bi bi-broom"></i> Clean Cache
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header" style="background: linear-gradient(135deg, #000 0%, #333 100%); color: white; border: none;">
                                    <h5 class="mb-0">
                                        <i class="bi bi-shield-check me-2"></i>Backup & Security
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <!-- Create Backup -->
                                    <form method="POST" class="mb-3">
                                        <input type="hidden" name="action" value="create_backup">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-archive text-success me-2"></i>
                                            <strong>Create Backup</strong>
                                        </div>
                                        <p class="text-muted small mb-2">Create a full backup of all content and uploads</p>
                                        <button type="submit" class="btn btn-dark btn-sm">
                                            <i class="bi bi-archive"></i> Create Backup
                                        </button>
                                    </form>

                                    <!-- System Health -->
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-heart-pulse text-danger me-2"></i>
                                            <strong>System Health</strong>
                                        </div>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="border rounded p-2">
                                                    <div class="text-dark"><?php echo round(disk_free_space(__DIR__) / 1024 / 1024 / 1024, 1); ?>GB</div>
                                                    <small class="text-muted">Free Space</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="border rounded p-2">
                                                    <div class="text-dark"><?php echo round(memory_get_usage() / 1024 / 1024, 1); ?>MB</div>
                                                    <small class="text-muted">Memory</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="border rounded p-2">
                                                    <div class="text-dark"><?php echo PHP_VERSION; ?></div>
                                                    <small class="text-muted">PHP</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Backups -->
                    <?php if (!empty($backups)): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header" style="background: linear-gradient(135deg, #000 0%, #333 100%); color: white; border: none;">
                                        <h5 class="mb-0">
                                            <i class="bi bi-archive me-2"></i>Recent Backups
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>File Name</th>
                                                        <th>Size</th>
                                                        <th>Date</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($backups, 0, 5) as $backup): ?>
                                                        <tr>
                                                            <td>
                                                                <i class="bi bi-file-zip text-primary me-2"></i>
                                                                <?php echo htmlspecialchars($backup['name']); ?>
                                                            </td>
                                                            <td><?php echo round($backup['size'] / 1024 / 1024, 2); ?> MB</td>
                                                            <td><?php echo date('M j, Y g:i A', $backup['date']); ?></td>
                                                            <td>
                                                                <a href="backups/<?php echo urlencode($backup['name']); ?>"
                                                                    class="btn btn-sm btn-outline-dark" download>
                                                                    <i class="bi bi-download"></i> Download
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>