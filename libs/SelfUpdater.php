<?php

class SelfUpdater
{
    /**
     * Update from a PUBLIC GitHub repository. No token needed.
     * Accepts owner/repo or full GitHub URL. If branch is empty, resolves default_branch.
     */
    public static function updateFromPublicRepo($repo, $branch = '')
    {
        $repo = trim($repo);
        if ($repo === '') {
            return ['success' => false, 'error' => 'Repository not provided'];
        }

        // Normalize full URL to owner/repo
        if (stripos($repo, 'github.com') !== false) {
            $u = @parse_url($repo);
            $p = isset($u['path']) ? trim($u['path'], "/ ") : '';
            if ($p !== '') {
                $seg = explode('/', $p);
                if (count($seg) >= 2) {
                    $ownerPart = $seg[0];
                    $repoPart = preg_replace('/\.git$/i', '', $seg[1]);
                    $repo = $ownerPart . '/' . $repoPart;
                }
            }
        }
        if (strpos($repo, '/') === false) {
            return ['success' => false, 'error' => 'Invalid repo format. Use owner/repo or GitHub URL'];
        }

        list($owner, $name) = explode('/', $repo, 2);
        $owner = trim($owner);
        $name = preg_replace('/\.git$/i', '', trim($name));

        // Resolve default branch if none provided
        $resolvedBranch = trim($branch);
        if ($resolvedBranch === '') {
            $apiUrl = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($name);
            $json = self::httpGetJson($apiUrl);
            if (is_array($json) && !empty($json['default_branch'])) {
                $resolvedBranch = $json['default_branch'];
            } else {
                // Fallback guesses
                $resolvedBranch = 'main';
            }
        }

        // Try git-based update first if possible
        $projectRoot = dirname(__DIR__);
        $git = self::findGitBinary();
        $gitAttemptLogs = [];
        if ($git) {
            $repoUrl = self::normalizeRepoUrl($owner, $name);
            $gitResult = self::updateViaGit($git, $projectRoot, $repoUrl, $resolvedBranch);
            if (!empty($gitResult['logs'])) {
                $gitAttemptLogs = $gitResult['logs'];
            }
            if ($gitResult['success']) {
                // Ensure mode is marked
                $gitResult['mode'] = 'git';
                return $gitResult; // Updated via git
            }
            // If git failed, fall back to ZIP method below and include git logs
        }

        $tmpDir = CONTENT_DIR . 'tmp_updater/';
        $zipFile = $tmpDir . 'update.zip';
        $extractDir = $tmpDir . 'extract/';

        self::rrmdir($tmpDir);
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        // Use codeload which doesn't require auth
        $downloadUrl = 'https://codeload.github.com/' . rawurlencode($owner) . '/' . rawurlencode($name) . '/zip/refs/heads/' . rawurlencode($resolvedBranch);
        $dl = self::download($downloadUrl, $zipFile, '');
        if (!$dl['success']) {
            return ['success' => false, 'error' => $dl['error'] ?? 'Download failed', 'mode' => 'zip', 'logs' => array_merge($gitAttemptLogs, [
                ['step' => 'download', 'url' => $downloadUrl, 'error' => $dl['error'] ?? 'download error']
            ])];
        }

        $ok = self::unzip($zipFile, $extractDir);
        if (!$ok['success']) {
            return ['success' => false, 'error' => $ok['error'] ?? 'Unzip failed', 'mode' => 'zip', 'logs' => array_merge($gitAttemptLogs, [
                ['step' => 'unzip', 'zip' => $zipFile, 'error' => $ok['error'] ?? 'unzip error']
            ])];
        }

        // Find source directory
        $dirEntries = glob($extractDir . '*', GLOB_ONLYDIR);
        $sourceDir = $dirEntries && is_dir($dirEntries[0]) ? rtrim($dirEntries[0], '\\/') : rtrim($extractDir, '\\/');

        // Copy files while preserving user data
        $excludes = [
            '/content/',
            '/uploads/',
            '/logs/',
            '/config.php',
            '/content/settings.json',
            '/install.php'
        ];
        $copy = self::copyRecursive($sourceDir, dirname(__DIR__), $excludes);
        if (!$copy['success']) {
            return ['success' => false, 'error' => $copy['error'] ?? 'Copy failed', 'mode' => 'zip', 'logs' => array_merge($gitAttemptLogs, [
                ['step' => 'copy', 'from' => $sourceDir, 'to' => dirname(__DIR__), 'error' => $copy['error'] ?? 'copy error']
            ])];
        }
        // Ensure install.php is not present post-update
        $removedInstall = false;
        $installPath = dirname(__DIR__) . '/install.php';
        if (file_exists($installPath)) {
            $removedInstall = @unlink($installPath);
        }

        self::rrmdir($tmpDir);
        return ['success' => true, 'message' => 'Updated from ' . $owner . '/' . $name . '@' . $resolvedBranch, 'mode' => 'zip', 'logs' => array_merge($gitAttemptLogs, [
            ['step' => 'download', 'url' => $downloadUrl, 'ok' => true],
            ['step' => 'unzip', 'zip' => $zipFile, 'ok' => true],
            ['step' => 'copy', 'from' => $sourceDir, 'to' => dirname(__DIR__), 'ok' => true],
            ['step' => 'cleanup', 'dir' => $tmpDir, 'ok' => true],
            ['step' => 'post', 'action' => 'remove install.php', 'ok' => $removedInstall]
        ])];
    }
    public static function updateFromURL($url, $checksum = '')
    {
        $url = trim($url);
        if ($url === '') {
            return ['success' => false, 'error' => 'Update URL not configured'];
        }

        $tmpDir = CONTENT_DIR . 'tmp_updater/';
        $zipFile = $tmpDir . 'update.zip';
        $extractDir = $tmpDir . 'extract/';

        self::rrmdir($tmpDir);
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $dl = self::download($url, $zipFile, '');
        if (!$dl['success']) {
            return $dl;
        }
        if ($checksum) {
            $hash = @hash_file('sha256', $zipFile) ?: '';
            if (!$hash || strcasecmp($hash, $checksum) !== 0) {
                @unlink($zipFile);
                return ['success' => false, 'error' => 'Checksum mismatch'];
            }
        }

        $ok = self::unzip($zipFile, $extractDir);
        if (!$ok['success']) {
            return $ok;
        }

        $entries = glob($extractDir . '*', GLOB_ONLYDIR);
        if (!$entries) {
            // Some zips may have flat structure; allow extractDir directly
            $sourceDir = rtrim($extractDir, '\/');
        } else {
            $sourceDir = rtrim($entries[0], '\/');
        }

        $excludes = [
            '/content/',
            '/uploads/',
            '/logs/',
            '/config.php',
            '/content/settings.json',
            '/install.php'
        ];
        $copy = self::copyRecursive($sourceDir, dirname(__DIR__), $excludes);
        if (!$copy['success']) {
            return $copy;
        }
        // Ensure install.php is not present post-update
        $installPath = dirname(__DIR__) . '/install.php';
        if (file_exists($installPath)) {
            @unlink($installPath);
        }
        self::rrmdir($tmpDir);
        return ['success' => true, 'message' => 'Updated from URL'];
    }

    public static function updateFromZipFile($zipPath)
    {
        $zipPath = trim($zipPath);
        if (!is_file($zipPath)) {
            return ['success' => false, 'error' => 'ZIP file not found'];
        }
        $tmpDir = CONTENT_DIR . 'tmp_updater/';
        $extractDir = $tmpDir . 'extract/';
        self::rrmdir($tmpDir);
        @mkdir($tmpDir, 0755, true);
        $ok = self::unzip($zipPath, $extractDir);
        if (!$ok['success']) return $ok;
        $entries = glob($extractDir . '*', GLOB_ONLYDIR);
        $sourceDir = $entries ? rtrim($entries[0], '\/') : rtrim($extractDir, '\/');
        $excludes = ['/content/', '/uploads/', '/logs/', '/config.php', '/content/settings.json', '/install.php'];
        $copy = self::copyRecursive($sourceDir, dirname(__DIR__), $excludes);
        if (!$copy['success']) return $copy;
        // Ensure install.php is not present post-update
        $installPath = dirname(__DIR__) . '/install.php';
        if (file_exists($installPath)) {
            @unlink($installPath);
        }
        self::rrmdir($tmpDir);
        return ['success' => true, 'message' => 'Updated from uploaded ZIP'];
    }

    public static function updateFromGitHub($repo, $branch, $token)
    {
        $repo = trim($repo);
        // Strip accidental leading '@' from pasted handles/links
        $repo = ltrim($repo, "@ \t\n\r\0\x0B");
        // Normalize: allow full GitHub URL or owner/repo
        if (stripos($repo, 'github.com') !== false) {
            $u = @parse_url($repo);
            $p = isset($u['path']) ? trim($u['path'], "/ ") : '';
            if ($p !== '') {
                $seg = explode('/', $p);
                if (count($seg) >= 2) {
                    $ownerPart = $seg[0];
                    $repoPart = preg_replace('/\.git$/i', '', $seg[1]);
                    $repo = $ownerPart . '/' . $repoPart;
                }
            }
        }
        $branch = trim($branch ?: 'main');
        if ($repo === '') {
            return ['success' => false, 'error' => 'Updater repo not configured'];
        }

        // Allow env-based token fallback if none provided
        if ($token === '' || $token === null) {
            $envToken = getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN') ?: getenv('GITHUB_PAT');
            if ($envToken) {
                $token = $envToken;
            }
        }

        // Build API URL correctly: encode owner and repo separately, not the slash
        if (strpos($repo, '/') === false) {
            return ['success' => false, 'error' => 'Invalid repo format. Use owner/repo'];
        }
        list($owner, $name) = explode('/', $repo, 2);
        $zipUrls = [
            // API zipball (redirects to blob store)
            'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($name) . '/zipball/' . rawurlencode($branch),
            // Codeload direct
            'https://codeload.github.com/' . rawurlencode($owner) . '/' . rawurlencode($name) . '/zip/refs/heads/' . rawurlencode($branch),
            // Web archive URL
            'https://github.com/' . rawurlencode($owner) . '/' . rawurlencode($name) . '/archive/refs/heads/' . rawurlencode($branch) . '.zip'
        ];
        $tmpDir = CONTENT_DIR . 'tmp_updater/';
        $zipFile = $tmpDir . 'update.zip';
        $extractDir = $tmpDir . 'extract/';

        self::rrmdir($tmpDir);
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $lastError = '';
        $downloaded = false;
        foreach ($zipUrls as $tryUrl) {
            $dl = self::download($tryUrl, $zipFile, $token);
            if ($dl['success']) {
                $downloaded = true;
                break;
            }
            $lastError = $dl['error'] ?? 'Download failed';
        }
        if (!$downloaded) {
            return ['success' => false, 'error' => $lastError ?: 'Download failed'];
        }

        $ok = self::unzip($zipFile, $extractDir);
        if (!$ok['success']) {
            return $ok;
        }

        // Prefer the first top-level directory, but allow flat archives too
        $dirEntries = glob($extractDir . '*', GLOB_ONLYDIR);
        if ($dirEntries && is_dir($dirEntries[0])) {
            $sourceDir = rtrim($dirEntries[0], '\/');
        } else {
            // Some GitHub/codeload zips may extract files directly under extractDir
            $sourceDir = rtrim($extractDir, '\/');
        }

        // Copy files, excluding user content and sensitive files
        $excludes = [
            '/content/',
            '/uploads/',
            '/logs/',
            '/config.php',
            '/content/settings.json'
        ];

        $copy = self::copyRecursive($sourceDir, dirname(__DIR__), $excludes);
        if (!$copy['success']) {
            return $copy;
        }

        self::rrmdir($tmpDir);
        return ['success' => true, 'message' => 'Updated to latest from ' . $repo . '@' . $branch];
    }

    private static function httpGetJson($url)
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: FlatFile-Blog-Updater\r\nAccept: application/vnd.github+json",
                'timeout' => 15
            ]
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return null;
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    private static function download($url, $dest, $token)
    {
        $fp = @fopen($dest, 'w');
        if (!$fp) return ['success' => false, 'error' => 'Cannot write temp file'];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter([
            'User-Agent: FlatFile-Blog-Updater',
            $token ? ('Authorization: token ' . $token) : ''
        ]));
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $code < 200 || $code >= 300) {
            @unlink($dest);
            return ['success' => false, 'error' => 'Download failed: HTTP ' . $code . ($err ? (' - ' . $err) : '')];
        }
        return ['success' => true];
    }

    private static function findGitBinary()
    {
        // Avoid shell_exec on locked-down hosts; try proc_open via runProcess
        $candidates = ['git'];
        $winGit = 'C:\\Program Files\\Git\\bin\\git.exe';
        if (stripos(PHP_OS, 'WIN') === 0 && is_file($winGit)) {
            $candidates[] = '"' . $winGit . '"';
        }
        $cwd = dirname(__DIR__);
        foreach ($candidates as $bin) {
            $res = self::runProcess($bin . ' --version', $cwd);
            if (is_array($res) && ($res['code'] === 0) && (stripos(($res['stdout'] ?? '') . ($res['stderr'] ?? ''), 'git version') !== false)) {
                return $bin;
            }
        }
        return null;
    }

    private static function runProcess($command, $cwd)
    {
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        $procOpenAllowed = function_exists('proc_open') && !in_array('proc_open', $disabled, true);
        if ($procOpenAllowed) {
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            $process = @proc_open($command, $descriptorspec, $pipes, $cwd, null);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $status = proc_close($process);
                return ['command' => $command, 'code' => $status, 'stdout' => (string)$stdout, 'stderr' => (string)$stderr];
            }
        }
        // Fallback to shell_exec if available
        $shellAllowed = function_exists('shell_exec') && !in_array('shell_exec', $disabled, true);
        if ($shellAllowed) {
            $output = @shell_exec($command . ' 2>&1');
            $code = ($output === null) ? 1 : 0;
            return ['command' => $command, 'code' => $code, 'stdout' => (string)$output, 'stderr' => ''];
        }
        // No execution methods allowed
        return ['command' => $command, 'code' => 127, 'stdout' => '', 'stderr' => 'Execution disabled by server configuration'];
    }

    private static function normalizeRepoUrl($owner, $name)
    {
        // Use HTTPS URL to avoid credential prompts for public repos
        return 'https://github.com/' . rawurlencode($owner) . '/' . rawurlencode($name) . '.git';
    }

    private static function updateViaGit($git, $root, $repoUrl, $branch)
    {
        $logs = [];
        $gitDir = rtrim($root, '\\/') . DIRECTORY_SEPARATOR . '.git';
        if (is_dir($gitDir)) {
            // Ensure remote origin exists and points to repoUrl
            $remoteCheck = self::runProcess($git . ' remote get-url origin', $root);
            $logs[] = $remoteCheck;
            if ($remoteCheck['code'] !== 0) {
                $add = self::runProcess($git . ' remote add origin ' . escapeshellarg($repoUrl), $root);
                $logs[] = $add;
                if ($add['code'] !== 0) {
                    return ['success' => false, 'error' => 'git remote add failed: ' . $add['stderr'], 'logs' => $logs];
                }
            }
            // Fetch and hard reset to origin/branch (leave untracked files alone)
            $fetch = self::runProcess($git . ' fetch --prune origin', $root);
            $logs[] = $fetch;
            if ($fetch['code'] !== 0) {
                return ['success' => false, 'error' => 'git fetch failed: ' . $fetch['stderr'], 'logs' => $logs];
            }
            // Ensure branch exists locally, then reset
            $checkout = self::runProcess($git . ' checkout -B ' . escapeshellarg($branch) . ' --track origin/' . escapeshellarg($branch), $root);
            $logs[] = $checkout;
            if ($checkout['code'] !== 0) {
                // Try without --track if branch already exists
                $checkout = self::runProcess($git . ' checkout -B ' . escapeshellarg($branch), $root);
                $logs[] = $checkout;
                if ($checkout['code'] !== 0) {
                    return ['success' => false, 'error' => 'git checkout failed: ' . $checkout['stderr'], 'logs' => $logs];
                }
            }
            $reset = self::runProcess($git . ' reset --hard origin/' . escapeshellarg($branch), $root);
            $logs[] = $reset;
            if ($reset['code'] !== 0) {
                return ['success' => false, 'error' => 'git reset failed: ' . $reset['stderr'], 'logs' => $logs];
            }
            // Remove install.php if present after reset
            $installPath = $root . DIRECTORY_SEPARATOR . 'install.php';
            if (file_exists($installPath)) {
                $rmOk = @unlink($installPath);
                $logs[] = ['command' => 'remove install.php', 'code' => $rmOk ? 0 : 1, 'stdout' => $rmOk ? 'removed' : '', 'stderr' => $rmOk ? '' : 'failed'];
            }
            // Optional: submodules
            $sub = self::runProcess($git . ' submodule update --init --recursive', $root);
            $logs[] = $sub;
            return ['success' => true, 'message' => 'Updated via git to branch ' . $branch, 'logs' => $logs];
        }

        // Not a git repo: try shallow clone to temp and copy over (non-destructive)
        $tmpDir = CONTENT_DIR . 'tmp_updater_git/';
        $cloneDir = rtrim($tmpDir, '\\/') . DIRECTORY_SEPARATOR . 'clone';
        self::rrmdir($tmpDir);
        @mkdir($tmpDir, 0755, true);
        $cloneCmd = $git . ' clone --depth 1 --branch ' . escapeshellarg($branch) . ' ' . escapeshellarg($repoUrl) . ' ' . escapeshellarg($cloneDir);
        $clone = self::runProcess($cloneCmd, $tmpDir);
        $logs[] = $clone;
        if ($clone['code'] !== 0) {
            self::rrmdir($tmpDir);
            return ['success' => false, 'error' => 'git clone failed: ' . $clone['stderr'], 'logs' => $logs];
        }
        // Copy from clone into project root, excluding user data
        $excludes = ['/content/', '/uploads/', '/logs/', '/config.php', '/content/settings.json'];
        $copy = self::copyRecursive($cloneDir, $root, $excludes);
        self::rrmdir($tmpDir);
        if (!$copy['success']) {
            $logs[] = ['command' => 'copy', 'code' => $copy['success'] ? 0 : 1, 'stdout' => '', 'stderr' => $copy['error'] ?? 'copy error'];
            return ['success' => false, 'error' => ($copy['error'] ?? 'Copy failed'), 'logs' => $logs];
        }
        // Remove install.php if present after copy
        $installPath = $root . DIRECTORY_SEPARATOR . 'install.php';
        if (file_exists($installPath)) {
            $rmOk = @unlink($installPath);
            $logs[] = ['command' => 'remove install.php', 'code' => $rmOk ? 0 : 1, 'stdout' => $rmOk ? 'removed' : '', 'stderr' => $rmOk ? '' : 'failed'];
        }
        $logs[] = ['command' => 'copy', 'code' => 0, 'stdout' => 'copied to ' . $root, 'stderr' => ''];
        return ['success' => true, 'message' => 'Updated via git clone to branch ' . $branch, 'logs' => $logs];
    }

    private static function unzip($zipFile, $extractTo)
    {
        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'error' => 'PHP ZipArchive not available'];
        }
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return ['success' => false, 'error' => 'Failed to open zip'];
        }
        @mkdir($extractTo, 0755, true);
        if (!$zip->extractTo($extractTo)) {
            $zip->close();
            return ['success' => false, 'error' => 'Failed to extract zip'];
        }
        $zip->close();
        return ['success' => true];
    }

    private static function copyRecursive($src, $dst, $excludes)
    {
        $src = rtrim($src, '\/');
        $dst = rtrim($dst, '\/');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $rel = str_replace($src, '', $item->getPathname());
            $rel = str_replace('\\', '/', $rel);
            foreach ($excludes as $ex) {
                if (stripos($rel, $ex) === 0) {
                    continue 2;
                }
            }
            $target = $dst . $rel;
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    @mkdir($target, 0755, true);
                }
            } else {
                // Ensure directory exists
                $dir = dirname($target);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (!@copy($item->getPathname(), $target)) {
                    return ['success' => false, 'error' => 'Failed to copy ' . $rel];
                }
            }
        }
        return ['success' => true];
    }

    private static function rrmdir($dir)
    {
        if (!is_dir($dir)) return;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }
        @rmdir($dir);
    }
}
