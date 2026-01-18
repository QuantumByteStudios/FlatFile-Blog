<?php

/**
 * Maintenance Tools
 * System maintenance and cleanup utilities
 */

// Check if blog is installed
$config_path = file_exists(__DIR__ . '/../config.php') ? __DIR__ . '/../config.php' : '../config.php';
if (!file_exists($config_path)) {
    header('Location: ../install.php');
    exit;
}

// Define constant to allow access
define('ALLOW_DIRECT_ACCESS', true);

try {
    $functions_path = file_exists(__DIR__ . '/../functions.php') ? __DIR__ . '/../functions.php' : '../functions.php';
    require_once $functions_path;
} catch (Exception $e) {
    error_log('Error loading functions.php: ' . $e->getMessage());
    die('System error. Please try again later.');
}

// Load SecurityHardener if available
$security_hardener_path = __DIR__ . '/../libs/SecurityHardener.php';
if (file_exists($security_hardener_path)) {
    require_once $security_hardener_path;
}

// Load AdminLogger if available
$admin_logger_path = __DIR__ . '/../libs/AdminLogger.php';
if (file_exists($admin_logger_path)) {
    require_once $admin_logger_path;
}

// Load backup tools if available
$backup_path = __DIR__ . '/tools/backup.php';
if (file_exists($backup_path)) {
    require_once $backup_path;
}

// Load SelfUpdater if available
$self_updater_path = __DIR__ . '/../libs/SelfUpdater.php';
if (file_exists($self_updater_path)) {
    require_once $self_updater_path;
}

// Initialize security system
if (class_exists('SecurityHardener')) {
    SecurityHardener::init();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set cache control headers to prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Ensure CSRF token exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
                if ($days < 1 || $days > 365) {
                    $error_message = "Invalid number of days (must be between 1 and 365).";
                } else {
                    AdminLogger::cleanOldLogs($days);
                    $success_message = "Cleaned logs older than {$days} days";
                }
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
                try {
                    $backup_result = BlogBackup::createBackup('manual');
                    if ($backup_result['success']) {
                        $success_message = 'Backup created successfully';
                    } else {
                        $error_message = 'Failed to create backup: ' . htmlspecialchars($backup_result['error'] ?? 'Unknown error', ENT_QUOTES, 'UTF-8');
                    }
                } catch (Exception $e) {
                    $error_message = 'Backup error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                }
                break;

            case 'clean_cache':
                // Clean cache files
                $cache_dir = __DIR__ . '/../cache/';
                $cleaned_count = 0;
                if (is_dir($cache_dir)) {
                    $files = glob($cache_dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            if (@unlink($file)) {
                                $cleaned_count++;
                            }
                        }
                    }
                }
                $success_message = "Cache cleaned successfully ({$cleaned_count} files removed)";
                break;

            case 'run_updater':
                // Verify CSRF token
                if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                    $error_message = "Invalid security token.";
                    break;
                }
                $settings = load_settings();
                $repo = trim($settings['updater_repo'] ?? 'https://github.com/QuantumByteStudios/FlatFile-Blog');
                if ($repo === '') {
                    $repo = 'https://github.com/QuantumByteStudios/FlatFile-Blog';
                }
                // Always use public repo with main branch
                $res = SelfUpdater::updateFromPublicRepo($repo, 'main');
                // Remove install.php regardless of updater path
                $extraLogs = [];
                $installPath = dirname(__DIR__) . '/install.php';
                if (file_exists($installPath)) {
                    $rmOk = @unlink($installPath);
                    $extraLogs[] = ['step' => 'post', 'action' => 'remove install.php (controller)', 'ok' => $rmOk];
                }

                // Save last update time if successful
                if ($res['success']) {
                    $settings['last_update_time'] = date('c');
                    $settings_file = CONTENT_DIR . 'settings.json';
                    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }

                // Prepare console logging via URL params
                $logsParam = '';
                if (!empty($res['logs']) || !empty($extraLogs)) {
                    $allLogs = !empty($res['logs']) ? array_merge($res['logs'], $extraLogs) : $extraLogs;
                    $logsParam = '&updater_logs=' . rawurlencode(json_encode($allLogs));
                }
                $modeParam = !empty($res['mode']) ? ('&updater_mode=' . rawurlencode($res['mode'])) : '';
                $msgParam = '&updater_msg=' . rawurlencode($res['message'] ?? ($res['error'] ?? ''));
                $target = BASE_URL . 'admin/admin-tools?';
                if ($res['success']) {
                    $target .= 'success=' . rawurlencode($res['message'] ?? 'Updated successfully.') . $modeParam . $logsParam . $msgParam;
                } else {
                    $target .= 'error=' . rawurlencode($res['error'] ?? 'Update failed.') . $modeParam . $logsParam . $msgParam;
                }
                header('Location: ' . $target);
                exit;

            default:
                $error_message = 'Invalid action';
        }
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Load settings for last update time
$ui_settings = load_settings();
$last_update_time = $ui_settings['last_update_time'] ?? null;

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
                    <div class="mb-5">
                        <h1 class="h3 mb-2 fw-bold">Maintenance Tools</h1>
                        <p class="text-muted mb-0">System maintenance and cleanup utilities</p>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <i class="bi bi-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Tools (flat 3-column layout) -->
                    <div class="row g-5">
                        <!-- Self-Updater -->
                        <div class="col-lg-4">
                            <div class="border-bottom border-2 pb-3 mb-4">
                                <h5 class="mb-0 fw-semibold">
                                    <i class="bi bi-cloud-arrow-down me-2 text-dark"></i>Self-Updater
                                </h5>
                            </div>

                            <?php if ($last_update_time): ?>
                                <div class="mb-4 p-3 bg-light rounded">
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="bi bi-clock-history text-dark me-2"></i>
                                        <span class="small fw-medium text-dark">Last Updated</span>
                                    </div>
                                    <div class="text-muted small">
                                        <?php echo date('M j, Y g:i A', strtotime($last_update_time)); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mb-4 p-3 bg-light rounded">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-info-circle text-muted me-2"></i>
                                        <span class="small text-muted">No updates yet</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="run_updater">
                                <input type="hidden" name="csrf_token"
                                    value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                <button type="submit" class="btn btn-dark btn-sm w-100">
                                    <i class="bi bi-cloud-arrow-down me-2"></i>Update Now
                                </button>
                            </form>

                            <p class="text-muted small mb-0">Pulls latest code from GitHub main branch. Content,
                                uploads, logs, and config are preserved.</p>
                        </div>

                        <!-- Maintenance -->
                        <div class="col-lg-4">
                            <div class="border-bottom border-2 pb-3 mb-4">
                                <h5 class="mb-0 fw-semibold">
                                    <i class="bi bi-tools me-2 text-dark"></i>System Maintenance
                                </h5>
                            </div>

                            <!-- Clean Logs -->
                            <div class="mb-4 pb-4 border-bottom">
                                <form method="POST">
                                    <input type="hidden" name="action" value="clean_logs">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="bi bi-trash text-dark me-2"></i>
                                        <strong class="fw-medium">Clean Old Logs</strong>
                                    </div>
                                    <div class="row g-2 mb-2">
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
                            </div>

                            <!-- Clean Cache -->
                            <div class="mb-4">
                                <form method="POST">
                                    <input type="hidden" name="action" value="clean_cache">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-broom text-dark me-2"></i>
                                        <strong class="fw-medium">Clean Cache</strong>
                                    </div>
                                    <p class="text-muted small mb-3">Clear all cached files and temporary data</p>
                                    <button type="submit" class="btn btn-dark btn-sm">
                                        <i class="bi bi-broom me-2"></i>Clean Cache
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Backup -->
                        <div class="col-lg-4">
                            <div class="border-bottom border-2 pb-3 mb-4">
                                <h5 class="mb-0 fw-semibold">
                                    <i class="bi bi-shield-check me-2 text-dark"></i>Backup & Security
                                </h5>
                            </div>

                            <!-- Create Backup -->
                            <div class="mb-4">
                                <form method="POST">
                                    <input type="hidden" name="action" value="create_backup">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-archive text-dark me-2"></i>
                                        <strong class="fw-medium">Create Backup</strong>
                                    </div>
                                    <p class="text-muted small mb-3">Create a full backup of all content and uploads</p>
                                    <button type="submit" class="btn btn-dark btn-sm">
                                        <i class="bi bi-archive me-2"></i>Create Backup
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>