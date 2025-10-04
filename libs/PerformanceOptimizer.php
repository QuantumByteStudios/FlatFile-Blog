<?php
/**
 * Performance Optimizer
 * Handles caching and performance optimizations
 */

class PerformanceOptimizer
{
    private static $cache_dir = __DIR__ . '/../cache/';
    private static $cache_lifetime = 3600; // 1 hour
    
    /**
     * Initialize cache directory
     */
    public static function init()
    {
        if (!file_exists(self::$cache_dir)) {
            mkdir(self::$cache_dir, 0755, true);
        }
    }
    
    /**
     * Get cached content
     */
    public static function get($key)
    {
        $file = self::$cache_dir . md5($key) . '.cache';
        
        if (file_exists($file) && (time() - filemtime($file)) < self::$cache_lifetime) {
            return unserialize(file_get_contents($file));
        }
        
        return false;
    }
    
    /**
     * Set cached content
     */
    public static function set($key, $data)
    {
        $file = self::$cache_dir . md5($key) . '.cache';
        return file_put_contents($file, serialize($data));
    }
    
    /**
     * Clear cache
     */
    public static function clear($key = null)
    {
        if ($key) {
            $file = self::$cache_dir . md5($key) . '.cache';
            if (file_exists($file)) {
                unlink($file);
            }
        } else {
            $files = glob(self::$cache_dir . '*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Set performance headers
     */
    public static function setHeaders($last_modified = null)
    {
        if ($last_modified) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
        }
        
        header('Cache-Control: public, max-age=300'); // 5 minutes
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
    }
    
    /**
     * Minify HTML
     */
    public static function minifyHtml($html)
    {
        // Remove comments
        $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);
        
        // Remove extra whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        
        return trim($html);
    }
    
    /**
     * Optimize images
     */
    public static function optimizeImage($source, $destination, $quality = 85)
    {
        $info = getimagesize($source);
        if (!$info) {
            return false;
        }
        
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
        
        if (!$image) {
            return false;
        }
        
        // Save optimized image
        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($image, $destination, $quality);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($image, $destination, 9 - ($quality / 10));
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($image, $destination);
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($image, $destination, $quality);
                break;
        }
        
        imagedestroy($image);
        return $result;
    }
    
    /**
     * Generate critical CSS
     */
    public static function getCriticalCSS()
    {
        return '
        body{font-family:system-ui,-apple-system,sans-serif;line-height:1.6;margin:0;padding:0}
        .container{max-width:1200px;margin:0 auto;padding:0 15px}
        .navbar{background:#343a40;color:#fff;padding:1rem 0}
        .card{border:1px solid #dee2e6;border-radius:0.375rem;margin-bottom:1rem}
        .btn{display:inline-block;padding:0.375rem 0.75rem;text-decoration:none;border-radius:0.25rem}
        .btn-primary{background:#007bff;color:#fff}
        .text-muted{color:#6c757d}
        ';
    }
}
?>
