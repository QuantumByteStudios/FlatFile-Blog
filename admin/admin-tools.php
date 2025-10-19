<?php

/**
 * Maintenance Tools
 * System maintenance and cleanup utilities
 */

require_once '../functions.php';
require_once __DIR__ . '/../libs/SecurityHardener.php';
require_once __DIR__ . '/../libs/AdminLogger.php';
require_once 'tools/backup.php';
require_once __DIR__ . '/../libs/SelfUpdater.php';

// Initialize security system
SecurityHardener::init();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

            case 'run_updater':
                $settings = load_settings();
                $repo = trim($settings['updater_repo'] ?? '');
                $token = trim($settings['updater_token'] ?? '');
                if ($repo === '') {
                    $error_message = 'Updater repository not configured in Settings.';
                    break;
                }
                // If no token, use public updater path; else use token-based path
                if ($token === '') {
                    $res = SelfUpdater::updateFromPublicRepo($repo, '');
                } else {
                    $res = SelfUpdater::updateFromGitHub($repo, '', $token);
                }
                // Remove install.php regardless of updater path
                $extraLogs = [];
                $installPath = dirname(__DIR__) . '/install.php';
                if (file_exists($installPath)) {
                    $rmOk = @unlink($installPath);
                    $extraLogs[] = ['step' => 'post', 'action' => 'remove install.php (controller)', 'ok' => $rmOk];
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

// Load settings once for UI (repo/token display)
$ui_settings = load_settings();
// Prepare friendly repo display/link and token status for the UI
$rawRepo = trim($ui_settings['updater_repo'] ?? '');
$displayRepo = $rawRepo;
$repoUrl = '';
if ($rawRepo !== '') {
    if (stripos($rawRepo, 'github.com') !== false) {
        $u = @parse_url($rawRepo);
        $p = isset($u['path']) ? trim($u['path'], "/ ") : '';
        if ($p !== '') {
            $seg = explode('/', $p);
            if (count($seg) >= 2) {
                $owner = $seg[0];
                $name = preg_replace('/\.git$/i', '', $seg[1]);
                $displayRepo = $owner . '/' . $name;
                $repoUrl = 'https://github.com/' . rawurlencode($owner) . '/' . rawurlencode($name);
            }
        }
    } elseif (strpos($rawRepo, '/') !== false) {
        list($owner, $name) = explode('/', $rawRepo, 2);
        $name = preg_replace('/\.git$/i', '', $name);
        $displayRepo = $owner . '/' . $name;
        $repoUrl = 'https://github.com/' . rawurlencode($owner) . '/' . rawurlencode($name);
    }
}
$tokenSet = !empty($ui_settings['updater_token']);
$repoConfigured = $displayRepo !== '';

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

                    <!-- Tools (compact 3-column layout) -->
                    <div class="row g-4">
                        <!-- Self-Updater -->
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header" style="background: linear-gradient(135deg, #000 0%, #333 100%); color: white; border: none;">
                                    <h5 class="mb-0">
                                        <i class="bi bi-cloud-arrow-down me-2"></i>Self-Updater
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2 small text-muted">
                                        Repo: <?php if (!empty($repoUrl)): ?>
                                            <a href="<?php echo htmlspecialchars($repoUrl); ?>" target="_blank" rel="noopener noreferrer">
                                                <code><?php echo htmlspecialchars($displayRepo); ?></code>
                                            </a>
                                        <?php else: ?>
                                            <code><?php echo htmlspecialchars($displayRepo ?: 'Not configured'); ?></code>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-2 small text-muted">
                                        Access: <span class="badge bg-<?php echo $tokenSet ? 'success' : 'info'; ?>"><?php echo $tokenSet ? 'Private repo access (token set)' : 'Public repo (no token needed)'; ?></span>
                                    </div>
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="action" value="run_updater">
                                        <button type="submit" class="btn btn-dark btn-sm w-100">
                                            <i class="bi bi-cloud-arrow-down"></i> Update Now
                                        </button>
                                    </form>
                                    <a href="<?php echo BASE_URL; ?>admin/settings#" class="btn btn-outline-dark btn-sm w-100 mt-2">
                                        <i class="bi bi-gear"></i> Configure
                                    </a>
                                    <div class="form-text mt-2">Pulls latest code from GitHub. Content, uploads, logs, and config are preserved.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance -->
                        <div class="col-lg-4">
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

                                    <!-- Simplified: only essential maintenance actions below -->
                                </div>
                            </div>
                        </div>

                        <!-- Backup -->
                        <div class="col-lg-4">
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

                                    <!-- System Health removed to keep tools minimal -->
                                </div>
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