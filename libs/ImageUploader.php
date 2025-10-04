<?php
/**
 * Secure Image Upload Handler
 * Handles image uploads with validation and security
 */

class ImageUploader
{
    private static $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    private static $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    /**
     * Upload and process image
     */
    public static function upload($file, $subfolder = '')
    {
        // Validate file
        $validation = self::validateFile($file);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        // Generate secure filename
        $extension = self::$allowedTypes[$file['type']];
        $filename = self::generateSecureFilename($extension);
        
        // Create directory structure
        $uploadDir = UPLOADS_DIR . $subfolder;
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filepath = $uploadDir . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Generate different sizes
            $sizes = self::generateImageSizes($filepath);
            
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $subfolder . '/' . $filename,
                'url' => BASE_URL . 'uploads/' . $subfolder . '/' . $filename,
                'sizes' => $sizes
            ];
        } else {
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private static function validateFile($file)
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload error: ' . $file['error']];
        }
        
        // Check file size
        if ($file['size'] > self::$maxFileSize) {
            return ['valid' => false, 'error' => 'File too large. Maximum size: ' . (self::$maxFileSize / 1024 / 1024) . 'MB'];
        }
        
        // Check MIME type
        if (!array_key_exists($file['type'], self::$allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', array_keys(self::$allowedTypes))];
        }
        
        // Verify file is actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'error' => 'File is not a valid image'];
        }
        
        // Check for suspicious content
        if (self::containsSuspiciousContent($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'File contains suspicious content'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Generate secure filename
     */
    private static function generateSecureFilename($extension)
    {
        $timestamp = date('Y-m-d_H-i-s');
        $random = bin2hex(random_bytes(8));
        return $timestamp . '_' . $random . '.' . $extension;
    }
    
    /**
     * Check for suspicious content
     */
    private static function containsSuspiciousContent($filepath)
    {
        $content = file_get_contents($filepath, false, null, 0, 1024);
        
        // Check for PHP tags
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            return true;
        }
        
        // Check for script tags
        if (stripos($content, '<script') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate different image sizes
     */
    private static function generateImageSizes($originalPath)
    {
        $sizes = [];
        
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            return $sizes;
        }
        
        $imageInfo = getimagesize($originalPath);
        
        if ($imageInfo === false) {
            return $sizes;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];
        
        // Create thumbnail (300x300)
        if ($width > 300 || $height > 300) {
            $thumbPath = str_replace('.', '_thumb.', $originalPath);
            if (self::resizeImage($originalPath, $thumbPath, 300, 300, $type)) {
                $sizes['thumbnail'] = basename($thumbPath);
            }
        }
        
        // Create medium size (800x600)
        if ($width > 800 || $height > 600) {
            $mediumPath = str_replace('.', '_medium.', $originalPath);
            if (self::resizeImage($originalPath, $mediumPath, 800, 600, $type)) {
                $sizes['medium'] = basename($mediumPath);
            }
        }
        
        return $sizes;
    }
    
    /**
     * Resize image
     */
    private static function resizeImage($source, $destination, $maxWidth, $maxHeight, $type)
    {
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            return false;
        }
        
        $imageInfo = getimagesize($source);
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
        
        // Create source image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($source);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($source);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($source);
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        // Create destination image
        $destImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize image
        imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save resized image
        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($destImage, $destination, 85);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($destImage, $destination, 8);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($destImage, $destination);
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($destImage, $destination, 85);
                break;
        }
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($destImage);
        
        return $result;
    }
    
    /**
     * Delete image and its variants
     */
    public static function delete($filepath)
    {
        $basePath = UPLOADS_DIR . $filepath;
        $deleted = [];
        
        // Delete original
        if (file_exists($basePath)) {
            unlink($basePath);
            $deleted[] = $basePath;
        }
        
        // Delete thumbnail
        $thumbPath = str_replace('.', '_thumb.', $basePath);
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
            $deleted[] = $thumbPath;
        }
        
        // Delete medium size
        $mediumPath = str_replace('.', '_medium.', $basePath);
        if (file_exists($mediumPath)) {
            unlink($mediumPath);
            $deleted[] = $mediumPath;
        }
        
        return $deleted;
    }
}
?>
