<?php
/**
 * Simple HTML Sanitizer
 * Basic HTML sanitization for Markdown output security
 */

class HTMLSanitizer
{
    private static $allowedTags = [
        'p', 'br', 'strong', 'em', 'u', 's', 'del', 'ins',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'blockquote', 'pre', 'code',
        'a', 'img',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div', 'span'
    ];
    
    private static $allowedAttributes = [
        'href', 'title', 'alt', 'src', 'width', 'height',
        'class', 'id', 'target', 'rel'
    ];
    
    /**
     * Sanitize HTML content
     */
    public static function sanitize($html)
    {
        // Remove script tags and their content
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        
        // Remove javascript: protocols
        $html = preg_replace('/javascript:/i', '', $html);
        
        // Remove on* event handlers
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        
        // Remove style attributes that might contain javascript
        $html = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $html);
        
        // Strip all tags except allowed ones
        $allowedTagsString = '<' . implode('><', self::$allowedTags) . '>';
        $html = strip_tags($html, $allowedTagsString);
        
        // Clean up attributes
        $html = self::cleanAttributes($html);
        
        return $html;
    }
    
    /**
     * Clean attributes from HTML tags
     */
    private static function cleanAttributes($html)
    {
        return preg_replace_callback('/<(\w+)([^>]*)>/i', function($matches) {
            $tag = $matches[1];
            $attributes = $matches[2];
            
            // Extract allowed attributes
            $cleanAttributes = [];
            foreach (self::$allowedAttributes as $attr) {
                if (preg_match('/\s' . preg_quote($attr, '/') . '\s*=\s*["\']([^"\']*)["\']/', $attributes, $attrMatches)) {
                    $value = $attrMatches[1];
                    
                    // Additional security checks
                    if ($attr === 'href' && !self::isValidUrl($value)) {
                        continue;
                    }
                    if ($attr === 'src' && !self::isValidImageSrc($value)) {
                        continue;
                    }
                    if ($attr === 'target' && $value !== '_blank') {
                        continue;
                    }
                    
                    $cleanAttributes[] = $attr . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
                }
            }
            
            $cleanAttrString = !empty($cleanAttributes) ? ' ' . implode(' ', $cleanAttributes) : '';
            return '<' . $tag . $cleanAttrString . '>';
        }, $html);
    }
    
    /**
     * Validate URL
     */
    private static function isValidUrl($url)
    {
        // Allow relative URLs and common protocols
        if (preg_match('/^(https?:\/\/|mailto:|#|\/)/', $url)) {
            return true;
        }
        return false;
    }
    
    /**
     * Validate image source
     */
    private static function isValidImageSrc($src)
    {
        // Allow relative URLs and data URIs for images
        if (preg_match('/^(https?:\/\/|data:image\/|\/)/', $src)) {
            return true;
        }
        return false;
    }
}
?>
