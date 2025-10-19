<?php

/**
 * Settings Page
 * Simple, essential settings only
 */

require_once '../functions.php';
require_once __DIR__ . '/../libs/SecurityHardener.php';
require_once __DIR__ . '/../libs/SelfUpdater.php';

// Ensure config constants are available when static analysis runs this file standalone
if (!defined('CONTENT_DIR') || !defined('BASE_URL') || !defined('SITE_TITLE')) {
    $cfg = file_exists('../config.php') ? '../config.php' : dirname(__DIR__) . '/config.php';
    if (file_exists($cfg)) {
        require_once $cfg;
    }
    if (!defined('CONTENT_DIR')) {
        define('CONTENT_DIR', dirname(__DIR__) . '/content/');
    }
    if (!defined('BASE_URL')) {
        define('BASE_URL', '/');
    }
    if (!defined('SITE_TITLE')) {
        define('SITE_TITLE', 'FlatFile Blog');
    }
}

// Initialize security system
SecurityHardener::init();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure CSRF token exists for this form
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Env fallback for OpenAI key (used for display and optional fallback)
$envOpenAI = getenv('OPENAI_API_KEY') ?: (getenv('GITHUB_MODELS_TOKEN') ?: getenv('GITHUB_TOKEN')) ?: '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $error_message = "Invalid CSRF token.";
        } else {
            // Load existing settings to preserve unknown keys and keep API key when empty
            $settings_file = CONTENT_DIR . 'settings.json';
            $existing_settings = [];
            if (file_exists($settings_file)) {
                $existing_settings = json_decode(file_get_contents($settings_file), true) ?: [];
            }

            // Compute sensitive and optional fields
            $business_info = trim($_POST['business_info'] ?? ($existing_settings['business_info'] ?? ''));
            // Prefer explicit POST value if non-empty; else keep saved; else fallback to env
            if (isset($_POST['openai_api_key']) && trim($_POST['openai_api_key']) !== '') {
                $openai_api_key = trim($_POST['openai_api_key']);
            } elseif (!empty($existing_settings['openai_api_key'])) {
                $openai_api_key = $existing_settings['openai_api_key'];
            } else {
                $openai_api_key = $envOpenAI;
            }
            $openai_model = trim($_POST['openai_model'] ?? ($existing_settings['openai_model'] ?? 'openai/gpt-4o-mini'));
            $openai_endpoint = trim($_POST['openai_endpoint'] ?? ($existing_settings['openai_endpoint'] ?? 'https://models.github.ai/inference'));
            $updater_repo = trim($_POST['updater_repo'] ?? ($existing_settings['updater_repo'] ?? ''));
            $updater_branch = trim($_POST['updater_branch'] ?? ($existing_settings['updater_branch'] ?? 'main'));
            $updater_token = trim($_POST['updater_token'] ?? ($existing_settings['updater_token'] ?? ''));
            $updater_url = trim($_POST['updater_url'] ?? ($existing_settings['updater_url'] ?? ''));
            $updater_checksum = trim($_POST['updater_checksum'] ?? ($existing_settings['updater_checksum'] ?? ''));

            // Merge updated settings
            $settings = array_merge($existing_settings, [
                'site_title' => trim($_POST['site_title'] ?? ''),
                'site_description' => trim($_POST['site_description'] ?? ''),
                'admin_email' => trim($_POST['admin_email'] ?? ''),
                'posts_per_page' => (int)($_POST['posts_per_page'] ?? 10),
                'business_info' => $business_info,
                'openai_api_key' => $openai_api_key,
                'openai_model' => $openai_model,
                'openai_endpoint' => $openai_endpoint,
                'updater_repo' => $updater_repo,
                'updater_branch' => $updater_branch,
                'updater_token' => $updater_token,
                'updater_url' => $updater_url,
                'updater_checksum' => $updater_checksum,
                'updated' => date('c')
            ]);

            // Save settings to file
            if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                $success_message = "Settings updated successfully!";

                // Update config.php if site title changed
                if ($settings['site_title'] !== SITE_TITLE) {
                    update_config_site_title($settings['site_title']);
                }
            } else {
                $error_message = "Failed to save settings.";
            }
        }
    } elseif ($_POST['action'] === 'run_updater') {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $error_message = "Invalid CSRF token.";
        } else {
            $s = load_settings();
            $repo = trim($s['updater_repo'] ?? '');
            $branch = trim($s['updater_branch'] ?? 'main');
            $token = trim($s['updater_token'] ?? '');
            if ($repo === '') {
                $error_message = 'Updater repository not configured in Settings.';
            } else {
                if ($token === '') {
                    $res = SelfUpdater::updateFromPublicRepo($repo, $branch);
                } else {
                    $res = SelfUpdater::updateFromGitHub($repo, $branch, $token);
                }
                if (!empty($res['success'])) {
                    $success_message = $res['message'] ?? 'Updated successfully.';
                } else {
                    $error_message = $res['error'] ?? 'Update failed.';
                }
            }
        }
    }
}

// Load current settings
$settings_file = CONTENT_DIR . 'settings.json';
$current_settings = [];
if (file_exists($settings_file)) {
    $current_settings = json_decode(file_get_contents($settings_file), true) ?: [];
}

// Default settings
$settings = array_merge([
    'site_title' => SITE_TITLE,
    'site_description' => 'A simple flat-file blog',
    'admin_email' => 'admin@example.com',
    'posts_per_page' => 10,
    'business_info' => '',
    'openai_api_key' => '',
    'openai_model' => 'openai/gpt-4o-mini',
    'openai_endpoint' => 'https://models.github.ai/inference',
    'updater_repo' => 'https://github.com/QuantumByteStudios/FlatFile-Blog',
    'updater_branch' => 'main',
    'updater_token' => '',
    'updater_url' => '',
    'updater_checksum' => ''
], $current_settings);

// If no saved key, show env fallback in the textbox for convenience
if ((trim($settings['openai_api_key'] ?? '') === '') && $envOpenAI !== '') {
    $settings['openai_api_key'] = $envOpenAI;
}

// Function to update config.php
function update_config_site_title($new_title)
{
    $config_file = dirname(__DIR__) . '/config.php';
    $config_content = file_get_contents($config_file);
    $config_content = preg_replace(
        "/define\('SITE_TITLE', '[^']*'\);/",
        "define('SITE_TITLE', '" . addslashes($new_title) . "');",
        $config_content
    );
    file_put_contents($config_file, $config_content);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo SITE_TITLE; ?></title>
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
                        <a class="nav-link text-light active" href="<?php echo BASE_URL; ?>admin/settings">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                        <a class="nav-link text-light" href="<?php echo BASE_URL; ?>admin/admin-tools">
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
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h1 class="h3 mb-0">
                                    <i class="bi bi-gear"></i> Settings
                                </h1>
                            </div>

                            <!-- Messages -->
                            <?php if (isset($success_message)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <!-- Settings Form -->
                            <form method="POST" action="<?php echo BASE_URL; ?>admin/settings">
                                <input type="hidden" name="action" value="update_settings">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                <div class="row">
                                    <div class="col-lg-12">
                                        <!-- Blog Information -->
                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <!-- Provider selection removed: Only OpenAI (GitHub Models) supported -->
                                                <div class="mb-3">
                                                    <label for="site_title" class="form-label">Site Title *</label>
                                                    <input type="text" class="form-control" id="site_title" name="site_title"
                                                        value="<?php echo htmlspecialchars($settings['site_title']); ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="site_description" class="form-label">Site Description</label>
                                                    <textarea class="form-control" id="site_description" name="site_description" rows="3"
                                                        placeholder="Brief description of your blog"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="admin_email" class="form-label">Admin Email</label>
                                                    <input type="email" class="form-control" id="admin_email" name="admin_email"
                                                        value="<?php echo htmlspecialchars($settings['admin_email']); ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label for="posts_per_page" class="form-label">Posts Per Page</label>
                                                    <input type="number" class="form-control" id="posts_per_page" name="posts_per_page"
                                                        value="<?php echo $settings['posts_per_page']; ?>" min="1" max="50">
                                                </div>


                                            </div>
                                        </div>

                                        <!-- AI Settings (OpenAI only) -->
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="bi bi-robot"></i> AI Settings</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label for="openai_api_key" class="form-label">GitHub Models Token</label>
                                                    <input type="text" class="form-control" id="openai_api_key" name="openai_api_key" value="<?php echo htmlspecialchars($settings['openai_api_key']); ?>" placeholder="Paste GitHub token">
                                                    <div class="form-text">Saved server-side.</div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="openai_model" class="form-label">OpenAI Model</label>
                                                    <input type="text" class="form-control" id="openai_model" name="openai_model" value="<?php echo htmlspecialchars($settings['openai_model']); ?>">
                                                    <div class="form-text">Examples: openai/gpt-4o-mini (low cost), openai/gpt-4o</div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="openai_endpoint" class="form-label">OpenAI Endpoint</label>
                                                    <input type="text" class="form-control" id="openai_endpoint" name="openai_endpoint" value="<?php echo htmlspecialchars($settings['openai_endpoint']); ?>">
                                                    <div class="form-text">Default: https://models.github.ai/inference</div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="business_info" class="form-label">Business Information</label>
                                                    <textarea class="form-control" id="business_info" name="business_info" rows="4" placeholder="Describe your business, audience, offerings, tone, location, etc."><?php echo htmlspecialchars($settings['business_info']); ?></textarea>
                                                    <div class="form-text">Used to personalize AI-generated posts.</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Self-Updater -->
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="bi bi-cloud-arrow-down"></i> Self-Updater</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row g-3 align-items-end">
                                                    <div class="col-md-6">
                                                        <label for="updater_repo" class="form-label">GitHub Repository (owner/repo or full URL)</label>
                                                        <input type="text" class="form-control" id="updater_repo" name="updater_repo" value="<?php echo htmlspecialchars($settings['updater_repo']); ?>" placeholder="https://github.com/QuantumByteStudios/FlatFile-Blog">
                                                        <div class="form-text">You can enter <code>owner/repo</code> or the full GitHub URL. The default is this repo.</div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="updater_token" class="form-label">GitHub Token</label>
                                                        <input type="text" class="form-control" id="updater_token" name="updater_token" value="<?php echo htmlspecialchars($settings['updater_token']); ?>" placeholder="Required for private repos">
                                                        <div class="form-text">Saved server-side; shown here if previously set.</div>
                                                    </div>
                                                </div>
                                                <div class="mt-3 small text-muted">
                                                    Updates pull the latest code from the repository's default branch (usually <code>main</code>). User content and <code>config.php</code> are preserved.
                                                </div>
                                                <div class="mt-3 d-flex justify-content-end">
                                                    <button type="submit" name="action" value="run_updater" class="btn btn-outline-dark">
                                                        <i class="bi bi-cloud-arrow-down"></i> Update Now
                                                    </button>
                                                </div>
                                                <script>
                                                    // Log updater debug info from URL params if present
                                                    (function() {
                                                        try {
                                                            var params = new URLSearchParams(window.location.search);
                                                            var ulogs = params.get('updater_logs');
                                                            var umsg = params.get('updater_msg');
                                                            var umode = params.get('updater_mode');
                                                            if (umsg) console.log('[Updater]', decodeURIComponent(umsg));
                                                            if (umode) console.log('[Updater] mode:', umode);
                                                            if (ulogs) {
                                                                try {
                                                                    var logs = JSON.parse(decodeURIComponent(ulogs));
                                                                    console.group('[Updater] logs');
                                                                    logs.forEach(function(entry, idx) {
                                                                        console.log('#' + (idx + 1), entry);
                                                                    });
                                                                    console.groupEnd();
                                                                } catch (e) {
                                                                    console.warn('[Updater] Failed to parse logs:', e);
                                                                }
                                                            }
                                                        } catch (e) {}
                                                    })();
                                                </script>
                                            </div>
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Save Settings
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <!-- No provider toggle needed -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>