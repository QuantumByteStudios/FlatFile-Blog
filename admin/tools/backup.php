<?php

/**
 * FlatFile Blog - Backup System
 * Automated backup with compression and rotation
 */

require_once __DIR__ . '/../../functions.php';

class BlogBackup
{
    private static $backup_dir = __DIR__ . '/../backups/';
    private static $max_backups = 30; // Keep 30 days of backups
    private static $compression_level = 6; // ZIP compression level (1-9)

    /**
     * Initialize backup system
     */
    public static function init()
    {
        if (!file_exists(self::$backup_dir)) {
            mkdir(self::$backup_dir, 0755, true);
        }
    }

    /**
     * Create full backup
     */
    public static function createBackup($type = 'full')
    {
        self::init();

        $timestamp = date('Y-m-d_H-i-s');
        $backup_name = "blog_backup_{$type}_{$timestamp}";
        $backup_path = self::$backup_dir . $backup_name . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($backup_path, ZipArchive::CREATE) !== TRUE) {
            return ['success' => false, 'error' => 'Cannot create backup file'];
        }

        try {
            // Backup content directory
            self::addDirectoryToZip($zip, CONTENT_DIR, 'content/');

            // Backup uploads directory
            if (file_exists(UPLOADS_DIR)) {
                self::addDirectoryToZip($zip, UPLOADS_DIR, 'uploads/');
            }

            // Backup configuration files
            $config_files = [
                'config.php',
                'functions.php',
                'index.php',
                'post.php',
                'admin.php',
                'admin_action.php',
                'search.php',
                'archive.php',
                'rss.php',
                'sitemap.php',
                '.htaccess',
                'robots.txt'
            ];

            foreach ($config_files as $file) {
                if (file_exists(__DIR__ . '/../' . $file)) {
                    $zip->addFile(__DIR__ . '/../' . $file, $file);
                }
            }

            // Backup libs directory
            if (file_exists(__DIR__ . '/../libs/')) {
                self::addDirectoryToZip($zip, __DIR__ . '/../libs/', 'libs/');
            }

            // Add backup metadata
            $metadata = [
                'timestamp' => date('c'),
                'type' => $type,
                'version' => '1.0',
                'files_count' => $zip->numFiles,
                'size' => 0
            ];

            $zip->addFromString('backup_info.json', json_encode($metadata, JSON_PRETTY_PRINT));

            $zip->close();

            // Get actual file size
            $file_size = filesize($backup_path);

            // Update metadata with actual size
            $metadata['size'] = $file_size;
            $metadata_json = json_encode($metadata, JSON_PRETTY_PRINT);

            // Reopen and update metadata
            $zip = new ZipArchive();
            if ($zip->open($backup_path, ZipArchive::CREATE) === TRUE) {
                $zip->addFromString('backup_info.json', $metadata_json);
                $zip->close();
            }

            // Clean old backups
            self::cleanOldBackups();

            return [
                'success' => true,
                'backup_file' => $backup_name . '.zip',
                'backup_path' => $backup_path,
                'size' => self::formatBytes($file_size),
                'files_count' => $zip->numFiles
            ];
        } catch (Exception $e) {
            $zip->close();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add directory to ZIP recursively
     */
    private static function addDirectoryToZip($zip, $dir, $zip_path)
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = $zip_path . substr($file_path, strlen($dir));
                $zip->addFile($file_path, $relative_path);
            }
        }
    }

    /**
     * Clean old backups
     */
    private static function cleanOldBackups()
    {
        $backups = glob(self::$backup_dir . 'blog_backup_*.zip');

        if (count($backups) > self::$max_backups) {
            // Sort by modification time (oldest first)
            usort($backups, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Remove oldest backups
            $to_remove = count($backups) - self::$max_backups;
            for ($i = 0; $i < $to_remove; $i++) {
                unlink($backups[$i]);
            }
        }
    }

    /**
     * List available backups
     */
    public static function listBackups()
    {
        self::init();

        $backups = glob(self::$backup_dir . 'blog_backup_*.zip');
        $backup_list = [];

        foreach ($backups as $backup) {
            $backup_list[] = [
                'filename' => basename($backup),
                'size' => self::formatBytes(filesize($backup)),
                'created' => date('Y-m-d H:i:s', filemtime($backup)),
                'path' => $backup
            ];
        }

        // Sort by creation time (newest first)
        usort($backup_list, function ($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });

        return $backup_list;
    }

    /**
     * Restore from backup
     */
    public static function restoreBackup($backup_file)
    {
        $backup_path = self::$backup_dir . $backup_file;

        if (!file_exists($backup_path)) {
            return ['success' => false, 'error' => 'Backup file not found'];
        }

        $zip = new ZipArchive();
        if ($zip->open($backup_path) !== TRUE) {
            return ['success' => false, 'error' => 'Cannot open backup file'];
        }

        try {
            // Extract to temporary directory first
            $temp_dir = sys_get_temp_dir() . '/blog_restore_' . uniqid();
            mkdir($temp_dir, 0755, true);

            $zip->extractTo($temp_dir);
            $zip->close();

            // Restore content directory
            if (file_exists($temp_dir . '/content/')) {
                self::copyDirectory($temp_dir . '/content/', CONTENT_DIR);
            }

            // Restore uploads directory
            if (file_exists($temp_dir . '/uploads/')) {
                self::copyDirectory($temp_dir . '/uploads/', UPLOADS_DIR);
            }

            // Restore configuration files
            $config_files = [
                'config.php',
                'functions.php',
                'index.php',
                'post.php',
                'admin.php',
                'admin_action.php',
                'search.php',
                'archive.php',
                'rss.php',
                'sitemap.php',
                '.htaccess',
                'robots.txt'
            ];

            foreach ($config_files as $file) {
                if (file_exists($temp_dir . '/' . $file)) {
                    copy($temp_dir . '/' . $file, __DIR__ . '/../' . $file);
                }
            }

            // Restore libs directory
            if (file_exists($temp_dir . '/libs/')) {
                self::copyDirectory($temp_dir . '/libs/', __DIR__ . '/../libs/');
            }

            // Clean up temporary directory
            self::removeDirectory($temp_dir);

            return ['success' => true, 'message' => 'Backup restored successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Copy directory recursively
     */
    private static function copyDirectory($src, $dst)
    {
        if (!file_exists($dst)) {
            mkdir($dst, 0755, true);
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($src));
                $dest_path = $dst . $relative_path;

                $dest_dir = dirname($dest_path);
                if (!file_exists($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                }

                copy($file_path, $dest_path);
            }
        }
    }

    /**
     * Remove directory recursively
     */
    private static function removeDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    /**
     * Format bytes to human readable
     */
    private static function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }
}

// Command line usage
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'create';

    switch ($action) {
        case 'create':
            $result = BlogBackup::createBackup();
            if ($result['success']) {
                echo "✅ Backup created successfully!\n";
                echo "File: {$result['backup_file']}\n";
                echo "Size: {$result['size']}\n";
                echo "Files: {$result['files_count']}\n";
            } else {
                echo "❌ Backup failed: {$result['error']}\n";
                exit(1);
            }
            break;

        case 'list':
            $backups = BlogBackup::listBackups();
            echo "Available backups:\n";
            foreach ($backups as $backup) {
                echo "- {$backup['filename']} ({$backup['size']}) - {$backup['created']}\n";
            }
            break;

        case 'restore':
            $backup_file = $argv[2] ?? '';
            if (empty($backup_file)) {
                echo "❌ Please specify backup file\n";
                exit(1);
            }

            $result = BlogBackup::restoreBackup($backup_file);
            if ($result['success']) {
                echo "✅ Backup restored successfully!\n";
            } else {
                echo "❌ Restore failed: {$result['error']}\n";
                exit(1);
            }
            break;

        default:
            echo "Usage: php backup.php [create|list|restore <file>]\n";
            break;
    }
}
