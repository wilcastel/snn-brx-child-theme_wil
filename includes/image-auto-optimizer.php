<?php
/**
 * Image Auto Optimizer
 * 
 * Automatically resizes and optimizes images when uploaded
 * Solves Lighthouse issues: 3,200 KiB image size, oversized images, aspect ratio handling
 * Works with Bricks Builder and WordPress standard image sizes
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SNN_Image_Auto_Optimizer {
    
    private $options;
    private $max_file_size_mb = 1; // 1MB default
    private $max_width = 2560; // Desktop max
    private $max_height = 2560;
    private $compression_quality = 75; // Reducido de 85 a 75 para mejor compresión (recomendado para web)
    
    public function __construct() {
        $this->options = get_option('snn_image_optimizer_options', array());
        
        // Get settings with defaults
        $this->max_file_size_mb = $this->options['max_file_size_mb'] ?? 1;
        $this->max_width = $this->options['max_width'] ?? 2560;
        $this->max_height = $this->options['max_height'] ?? 2560;
        $this->compression_quality = $this->options['compression_quality'] ?? 75; // Default 75 para mejor compresión (recomendado para web)
        
        $this->init_hooks();
        $this->register_image_sizes();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook before WordPress processes the upload
        add_filter('wp_handle_upload_prefilter', array($this, 'preprocess_upload'));
        
        // Hook after WordPress generates image sizes
        add_filter('wp_generate_attachment_metadata', array($this, 'optimize_attachment_metadata'), 10, 3);
        
        // Ensure all image sizes are generated for Bricks compatibility
        add_filter('intermediate_image_sizes_advanced', array($this, 'add_custom_image_sizes'), 10, 1);
        
        // Admin settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add admin notice if optimization is disabled
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Register custom image sizes for better responsive handling
     * Más tamaños = mejor srcset = imágenes más pequeñas según dispositivo
     */
    private function register_image_sizes() {
        // Standard WordPress sizes (already exist, but ensure they're optimal)
        // Add custom sizes for desktop/mobile breakpoints
        
        // Tamaños móviles (para mejor srcset)
        add_image_size('snn_mobile_small', 320, 240, false); // Móvil pequeño (320px)
        add_image_size('snn_mobile', 480, 360, false); // Móvil estándar (480px)
        add_image_size('snn_mobile_large', 768, 576, false); // Móvil grande (768px)
        
        // Tamaños tablet
        add_image_size('snn_tablet', 1024, 768, false); // Tablet (1024px)
        
        // Tamaños desktop
        add_image_size('snn_desktop_small', 1280, 720, false); // Desktop pequeño (1280px)
        add_image_size('snn_desktop', 1920, 1080, false); // Desktop estándar (1920px)
        add_image_size('snn_desktop_large', 2560, 1440, false); // 2K screens (2560px)
        
        // Estos tamaños funcionan con imágenes verticales y horizontales
        // WordPress mantendrá la proporción (false = no crop)
    }
    
    /**
     * Preprocess upload: Resize if too large before WordPress processes it
     */
    public function preprocess_upload($file) {
        // Check if optimization is enabled
        if (!($this->options['enable_auto_optimization'] ?? true)) {
            return $file;
        }
        
        // Only process images
        $file_type = wp_check_filetype($file['name']);
        if (!in_array($file_type['type'], array('image/jpeg', 'image/png'))) {
            return $file;
        }
        
        // Check file size
        $file_size_mb = $file['size'] / 1048576; // Convert to MB
        
        if ($file_size_mb > $this->max_file_size_mb) {
            // Resize image before WordPress processes it
            $resized = $this->resize_image_file($file['tmp_name'], $file['type']);
            
            if ($resized) {
                // Update file size
                $file['size'] = filesize($file['tmp_name']);
            }
        }
        
        // Always optimize dimensions if image is too large
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info) {
            $width = $image_info[0];
            $height = $image_info[1];
            
            if ($width > $this->max_width || $height > $this->max_height) {
                $resized = $this->resize_image_file($file['tmp_name'], $file['type']);
                
                if ($resized) {
                    $file['size'] = filesize($file['tmp_name']);
                }
            }
        }
        
        return $file;
    }
    
    /**
     * Resize image file maintaining aspect ratio
     */
    private function resize_image_file($file_path, $mime_type) {
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
            return false;
        }
        
        $image_info = @getimagesize($file_path);
        if (!$image_info) {
            return false;
        }
        
        $original_width = $image_info[0];
        $original_height = $image_info[1];
        
        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($this->max_width / $original_width, $this->max_height / $original_height);
        
        // Only resize if needed
        if ($ratio >= 1.0) {
            return false; // No resize needed
        }
        
        $new_width = intval($original_width * $ratio);
        $new_height = intval($original_height * $ratio);
        
        // Load image based on type
        switch ($mime_type) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($file_path);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($file_path);
                break;
            default:
                return false;
        }
        
        if (!$image) {
            return false;
        }
        
        // Create new image
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG
        if ($mime_type === 'image/png') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
        } else {
            // White background for JPEG
            $white = imagecolorallocate($new_image, 255, 255, 255);
            imagefill($new_image, 0, 0, $white);
        }
        
        // Resize with high quality
        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
        
        // Save optimized image
        $success = false;
        switch ($mime_type) {
            case 'image/jpeg':
                $success = @imagejpeg($new_image, $file_path, $this->compression_quality);
                break;
            case 'image/png':
                // PNG compression level (0-9, 9 is max compression)
                $png_quality = 9 - intval(($this->compression_quality / 100) * 9);
                $success = @imagepng($new_image, $file_path, $png_quality);
                break;
        }
        
        imagedestroy($image);
        imagedestroy($new_image);
        
        return $success;
    }
    
    /**
     * Optimize attachment metadata and all generated sizes
     */
    public function optimize_attachment_metadata($metadata, $attachment_id, $context) {
        if (!($this->options['enable_auto_optimization'] ?? true)) {
            return $metadata;
        }
        
        if ($context !== 'create' && $context !== 'edit') {
            return $metadata;
        }
        
        // Get original file
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return $metadata;
        }
        
        // Optimize original if needed
        $this->optimize_image_file($file_path);
        
        // Optimize all generated sizes
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $upload_dir = wp_upload_dir();
            $file_info = pathinfo($metadata['file']);
            $base_dir = $upload_dir['basedir'] . '/' . $file_info['dirname'];
            
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                if (isset($size_data['file'])) {
                    $size_file_path = $base_dir . '/' . $size_data['file'];
                    if (file_exists($size_file_path)) {
                        $this->optimize_image_file($size_file_path);
                    }
                }
            }
        }
        
        return $metadata;
    }
    
    /**
     * Optimize a single image file (compression)
     */
    private function optimize_image_file($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        $mime_type = wp_check_filetype($file_path)['type'];
        if (!in_array($mime_type, array('image/jpeg', 'image/png'))) {
            return false;
        }
        
        // Only recompress if we have GD library
        if (!function_exists('imagecreatefromjpeg')) {
            return false;
        }
        
        // For JPEG, we can recompress to reduce file size
        // For PNG, we're limited but can try
        switch ($mime_type) {
            case 'image/jpeg':
                return $this->optimize_jpeg($file_path);
            case 'image/png':
                return $this->optimize_png($file_path);
        }
        
        return false;
    }
    
    /**
     * Optimize JPEG image
     */
    private function optimize_jpeg($file_path) {
        $image = @imagecreatefromjpeg($file_path);
        if (!$image) {
            return false;
        }
        
        // Get original file size
        $original_size = filesize($file_path);
        
        // Recompress with quality setting
        $success = @imagejpeg($image, $file_path, $this->compression_quality);
        imagedestroy($image);
        
        if ($success) {
            $new_size = filesize($file_path);
            // Only return true if we actually saved space
            return $new_size < $original_size;
        }
        
        return false;
    }
    
    /**
     * Optimize PNG image (limited optimization)
     */
    private function optimize_png($file_path) {
        // PNG optimization is more limited with GD
        // We mainly ensure proper compression level
        $image = @imagecreatefrompng($file_path);
        if (!$image) {
            return false;
        }
        
        $original_size = filesize($file_path);
        
        // Convert palette images to true color for better compression
        if (!imageistruecolor($image)) {
            $width = imagesx($image);
            $height = imagesy($image);
            $truecolor = imagecreatetruecolor($width, $height);
            
            imagealphablending($truecolor, false);
            imagesavealpha($truecolor, true);
            
            imagecopy($truecolor, $image, 0, 0, 0, 0, $width, $height);
            imagedestroy($image);
            $image = $truecolor;
        }
        
        $png_quality = 9 - intval(($this->compression_quality / 100) * 9);
        $success = @imagepng($image, $file_path, $png_quality);
        imagedestroy($image);
        
        if ($success) {
            $new_size = filesize($file_path);
            return $new_size < $original_size;
        }
        
        return false;
    }
    
    /**
     * Add custom image sizes to WordPress
     */
    public function add_custom_image_sizes($sizes) {
        // Ensure our custom sizes are always generated
        // WordPress will handle cropping proportionally for vertical/horizontal
        return $sizes; // Already registered via add_image_size, WordPress handles it
    }
    
    /**
     * Register admin settings
     */
    public function register_settings() {
        register_setting('snn_image_optimizer_options', 'snn_image_optimizer_options', array($this, 'sanitize_options'));
        
        add_settings_section(
            'snn_image_optimizer_section',
            __('Image Auto Optimization', 'snn'),
            array($this, 'section_callback'),
            'snn-webp-optimization' // Add to existing WebP page
        );
        
        add_settings_field(
            'enable_auto_optimization',
            __('Enable Auto Optimization', 'snn'),
            array($this, 'enable_auto_optimization_callback'),
            'snn-webp-optimization',
            'snn_image_optimizer_section'
        );
        
        add_settings_field(
            'max_file_size_mb',
            __('Max File Size (MB)', 'snn'),
            array($this, 'max_file_size_mb_callback'),
            'snn-webp-optimization',
            'snn_image_optimizer_section'
        );
        
        add_settings_field(
            'max_width',
            __('Max Width (px)', 'snn'),
            array($this, 'max_width_callback'),
            'snn-webp-optimization',
            'snn_image_optimizer_section'
        );
        
        add_settings_field(
            'max_height',
            __('Max Height (px)', 'snn'),
            array($this, 'max_height_callback'),
            'snn-webp-optimization',
            'snn_image_optimizer_section'
        );
        
        add_settings_field(
            'compression_quality',
            __('Compression Quality', 'snn'),
            array($this, 'compression_quality_callback'),
            'snn-webp-optimization',
            'snn_image_optimizer_section'
        );
    }
    
    /**
     * Section callback
     */
    public function section_callback() {
        echo '<p>' . __('Automatically resize and optimize images when uploaded. Works for both vertical and horizontal images. Compatible with Bricks Builder.', 'snn') . '</p>';
    }
    
    /**
     * Field callbacks
     */
    public function enable_auto_optimization_callback() {
        $enabled = isset($this->options['enable_auto_optimization']) ? $this->options['enable_auto_optimization'] : true;
        echo '<input type="checkbox" name="snn_image_optimizer_options[enable_auto_optimization]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Automatically resize images larger than max dimensions and optimize file size.', 'snn') . '</p>';
    }
    
    public function max_file_size_mb_callback() {
        $value = $this->options['max_file_size_mb'] ?? 1;
        echo '<input type="number" name="snn_image_optimizer_options[max_file_size_mb]" value="' . esc_attr($value) . '" min="0.5" max="10" step="0.1" />';
        echo '<p class="description">' . __('Maximum file size in MB. Images larger than this will be resized automatically.', 'snn') . '</p>';
    }
    
    public function max_width_callback() {
        $value = $this->options['max_width'] ?? 2560;
        echo '<input type="number" name="snn_image_optimizer_options[max_width]" value="' . esc_attr($value) . '" min="500" max="4000" step="10" />';
        echo '<p class="description">' . __('Maximum width in pixels. Images wider than this will be resized maintaining aspect ratio.', 'snn') . '</p>';
    }
    
    public function max_height_callback() {
        $value = $this->options['max_height'] ?? 2560;
        echo '<input type="number" name="snn_image_optimizer_options[max_height]" value="' . esc_attr($value) . '" min="500" max="4000" step="10" />';
        echo '<p class="description">' . __('Maximum height in pixels. Images taller than this will be resized maintaining aspect ratio.', 'snn') . '</p>';
    }
    
    public function compression_quality_callback() {
        $value = $this->options['compression_quality'] ?? 75;
        echo '<input type="number" name="snn_image_optimizer_options[compression_quality]" value="' . esc_attr($value) . '" min="60" max="100" step="1" />';
        echo '<p class="description">' . __('JPEG compression quality (60-100). Lower = smaller files but lower quality. Recommended: 75 para web (balance óptimo calidad/tamaño).', 'snn') . '</p>';
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        $sanitized['enable_auto_optimization'] = isset($input['enable_auto_optimization']) ? 1 : 0;
        $sanitized['max_file_size_mb'] = floatval($input['max_file_size_mb'] ?? 1);
        $sanitized['max_width'] = intval($input['max_width'] ?? 2560);
        $sanitized['max_height'] = intval($input['max_height'] ?? 2560);
        $sanitized['compression_quality'] = intval($input['compression_quality'] ?? 75);
        
        // Validate ranges
        if ($sanitized['max_file_size_mb'] < 0.5) $sanitized['max_file_size_mb'] = 0.5;
        if ($sanitized['max_file_size_mb'] > 10) $sanitized['max_file_size_mb'] = 10;
        if ($sanitized['max_width'] < 500) $sanitized['max_width'] = 500;
        if ($sanitized['max_height'] < 500) $sanitized['max_height'] = 500;
        if ($sanitized['compression_quality'] < 60) $sanitized['compression_quality'] = 60;
        if ($sanitized['compression_quality'] > 100) $sanitized['compression_quality'] = 100;
        
        return $sanitized;
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        if (!($this->options['enable_auto_optimization'] ?? true)) {
            $screen = get_current_screen();
            if ($screen && $screen->id === 'settings_page_snn-webp-optimization') {
                echo '<div class="notice notice-warning"><p>';
                echo __('⚠️ Auto Image Optimization is disabled. Large images may not be automatically resized.', 'snn');
                echo '</p></div>';
            }
        }
    }
}

// Initialize
new SNN_Image_Auto_Optimizer();





