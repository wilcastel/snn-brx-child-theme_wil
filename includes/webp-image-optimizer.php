<?php
/**
 * WebP Image Optimization System
 * 
 * Converts and optimizes images to WebP format for better Core Web Vitals
 * Includes automatic conversion, lazy loading, and critical image preloading
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SNN_WebP_Image_Optimizer {
    
    private $options;
    private $upload_dir;
    private $webp_dir;
    private $quality;
    private $max_width;
    private $max_height;
    
    public function __construct() {
        $this->options = get_option('snn_webp_options', array());
        $this->upload_dir = wp_upload_dir();
        $this->webp_dir = $this->upload_dir['basedir'] . '/webp/';
        $this->quality = $this->options['webp_quality'] ?? 85;
        $this->max_width = $this->options['max_width'] ?? 1920;
        $this->max_height = $this->options['max_height'] ?? 1080;
        
        $this->init_hooks();
        $this->create_webp_directory();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Image upload hooks
        add_action('wp_handle_upload', array($this, 'convert_uploaded_image'));
        add_action('attachment_updated', array($this, 'convert_attachment_image'));
        
        // Frontend hooks
        add_filter('wp_get_attachment_image_src', array($this, 'replace_with_webp'), 10, 4);
        add_filter('the_content', array($this, 'replace_content_images'));
        add_action('wp_head', array($this, 'preload_critical_images'));
        
        // Admin hooks
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_snn_convert_images', array($this, 'convert_images_ajax'));
        add_action('wp_ajax_snn_optimize_images', array($this, 'optimize_images_ajax'));
        add_action('wp_ajax_snn_start_background_conversion', array($this, 'start_background_conversion'));
        add_action('wp_ajax_snn_get_conversion_status', array($this, 'get_conversion_status'));
        
        // WP Cron hooks for background processing
        add_action('snn_webp_background_conversion', array($this, 'process_background_conversion'));
        add_action('init', array($this, 'schedule_background_conversion'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
        // Lazy loading
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lazy_loading'), 10, 3);
    }
    
    /**
     * Create WebP directory
     */
    private function create_webp_directory() {
        if (!file_exists($this->webp_dir)) {
            wp_mkdir_p($this->webp_dir);
            
            // Add .htaccess for security
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($this->webp_dir . '.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Convert uploaded image to WebP
     */
    public function convert_uploaded_image($upload) {
        if (!($this->options['enable_webp'] ?? true)) {
            return $upload;
        }
        
        $file_path = $upload['file'];
        $file_type = wp_check_filetype($file_path);
        
        // Only convert supported image types
        if (in_array($file_type['type'], array('image/jpeg', 'image/png'))) {
            $this->convert_image_to_webp($file_path);
        }
        
        return $upload;
    }
    
    /**
     * Convert attachment image to WebP
     */
    public function convert_attachment_image($attachment_id) {
        if (!($this->options['enable_webp'] ?? true)) {
            return;
        }
        
        $file_path = get_attached_file($attachment_id);
        if ($file_path && file_exists($file_path)) {
            $this->convert_image_to_webp($file_path);
        }
    }
    
    /**
     * Convert image to WebP format
     */
    private function convert_image_to_webp($file_path) {
        if (!function_exists('imagewebp')) {
            return false;
        }
        
        $file_info = pathinfo($file_path);
        $webp_path = $this->webp_dir . $file_info['filename'] . '.webp';
        
        // Skip if WebP already exists and is newer
        if (file_exists($webp_path) && filemtime($webp_path) >= filemtime($file_path)) {
            return $webp_path;
        }
        
        $image_type = exif_imagetype($file_path);
        
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($file_path);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($file_path);
                break;
            default:
                return false;
        }
        
        if (!$image) {
            return false;
        }
        
        // Resize if needed
        $image = $this->resize_image_if_needed($image, $file_path);
        
        // Convert to WebP
        $success = imagewebp($image, $webp_path, $this->quality);
        imagedestroy($image);
        
        if ($success) {
            // Update file permissions
            chmod($webp_path, 0644);
            return $webp_path;
        }
        
        return false;
    }
    
    /**
     * Resize image if it exceeds maximum dimensions
     */
    private function resize_image_if_needed($image, $file_path) {
        $original_width = imagesx($image);
        $original_height = imagesy($image);
        
        // Check if resizing is needed
        if ($original_width <= $this->max_width && $original_height <= $this->max_height) {
            return $image;
        }
        
        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($this->max_width / $original_width, $this->max_height / $original_height);
        $new_width = intval($original_width * $ratio);
        $new_height = intval($original_height * $ratio);
        
        // Create new image
        $resized_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG
        if (exif_imagetype($file_path) === IMAGETYPE_PNG) {
            imagealphablending($resized_image, false);
            imagesavealpha($resized_image, true);
            $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);
            imagefilledrectangle($resized_image, 0, 0, $new_width, $new_height, $transparent);
        }
        
        // Resize image
        imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
        
        // Clean up original image
        imagedestroy($image);
        
        return $resized_image;
    }
    
    /**
     * Replace image source with WebP version
     */
    public function replace_with_webp($image, $attachment_id, $size, $icon) {
        if (!($this->options['enable_webp'] ?? true)) {
            return $image;
        }
        
        if (!$image || !$attachment_id) {
            return $image;
        }
        
        $file_path = get_attached_file($attachment_id);
        if (!$file_path) {
            return $image;
        }
        
        $file_info = pathinfo($file_path);
        $webp_path = $this->webp_dir . $file_info['filename'] . '.webp';
        
        // Check if WebP version exists
        if (file_exists($webp_path)) {
            $webp_url = $this->upload_dir['baseurl'] . '/webp/' . $file_info['filename'] . '.webp';
            $image[0] = $webp_url;
        }
        
        return $image;
    }
    
    /**
     * Replace images in content with WebP versions
     */
    public function replace_content_images($content) {
        if (!($this->options['enable_webp'] ?? true)) {
            return $content;
        }
        
        // Find all img tags
        preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/i', $content, $matches);
        
        foreach ($matches[1] as $index => $image_url) {
            $webp_url = $this->get_webp_url($image_url);
            if ($webp_url) {
                $content = str_replace($image_url, $webp_url, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Get WebP URL for given image URL
     */
    private function get_webp_url($image_url) {
        $upload_url = $this->upload_dir['baseurl'];
        
        // Check if image is from uploads directory
        if (strpos($image_url, $upload_url) !== 0) {
            return false;
        }
        
        $relative_path = str_replace($upload_url, '', $image_url);
        $file_info = pathinfo($relative_path);
        
        $webp_path = $this->webp_dir . $file_info['filename'] . '.webp';
        
        if (file_exists($webp_path)) {
            return $upload_url . '/webp/' . $file_info['filename'] . '.webp';
        }
        
        return false;
    }
    
    /**
     * Preload critical images
     */
    public function preload_critical_images() {
        if (!($this->options['enable_preload'] ?? true)) {
            return;
        }
        
        $critical_images = $this->get_critical_images();
        
        foreach ($critical_images as $image_url) {
            echo '<link rel="preload" as="image" href="' . esc_url($image_url) . '">' . "\n";
        }
    }
    
    /**
     * Get critical images for preloading
     */
    private function get_critical_images() {
        $critical_images = array();
        
        // Get featured image of current post
        if (is_singular()) {
            $featured_image_id = get_post_thumbnail_id();
            if ($featured_image_id) {
                $featured_image_url = wp_get_attachment_image_url($featured_image_id, 'large');
                if ($featured_image_url) {
                    $critical_images[] = $featured_image_url;
                }
            }
        }
        
        // Get logo
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo_url) {
                $critical_images[] = $logo_url;
            }
        }
        
        // Get custom critical images from options
        $custom_images = $this->options['critical_images'] ?? '';
        if ($custom_images) {
            $lines = explode("\n", $custom_images);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line && filter_var($line, FILTER_VALIDATE_URL)) {
                    $critical_images[] = $line;
                }
            }
        }
        
        return array_unique($critical_images);
    }
    
    /**
     * Add lazy loading attributes
     */
    public function add_lazy_loading($attr, $attachment, $size) {
        if (!($this->options['enable_lazy_loading'] ?? true)) {
            return $attr;
        }
        
        // Skip lazy loading for critical images
        $critical_images = $this->get_critical_images();
        $current_image_url = wp_get_attachment_image_url($attachment->ID, $size);
        
        if (in_array($current_image_url, $critical_images)) {
            return $attr;
        }
        
        // Add lazy loading attributes
        $attr['loading'] = 'lazy';
        $attr['decoding'] = 'async';
        
        // Add placeholder
        if ($this->options['enable_placeholder'] ?? true) {
            $attr['data-src'] = $attr['src'];
            $attr['src'] = $this->get_placeholder_image($attachment->ID);
            $attr['class'] = ($attr['class'] ?? '') . ' snn-lazy-image';
        }
        
        return $attr;
    }
    
    /**
     * Get placeholder image
     */
    private function get_placeholder_image($attachment_id) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata) {
            return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMSIgaGVpZ2h0PSIxIiB2aWV3Qm94PSIwIDAgMSAxIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxIiBoZWlnaHQ9IjEiIGZpbGw9IiNmM2Y0ZjYiLz48L3N2Zz4=';
        }
        
        $width = $metadata['width'] ?? 1;
        $height = $metadata['height'] ?? 1;
        
        return "data:image/svg+xml;base64," . base64_encode(
            '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="' . $width . '" height="' . $height . '" fill="#f3f4f6"/></svg>'
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('snn_webp_options', 'snn_webp_options', array($this, 'sanitize_options'));
        
        add_settings_section(
            'snn_webp_general',
            __('General Settings', 'snn'),
            array($this, 'general_section_callback'),
            'snn-webp-optimization'
        );
        
        add_settings_field(
            'enable_webp',
            __('Enable WebP Conversion', 'snn'),
            array($this, 'enable_webp_callback'),
            'snn-webp-optimization',
            'snn_webp_general'
        );
        
        add_settings_field(
            'webp_quality',
            __('WebP Quality', 'snn'),
            array($this, 'webp_quality_callback'),
            'snn-webp-optimization',
            'snn_webp_general'
        );
        
        add_settings_field(
            'max_width',
            __('Maximum Width', 'snn'),
            array($this, 'max_width_callback'),
            'snn-webp-optimization',
            'snn_webp_general'
        );
        
        add_settings_field(
            'max_height',
            __('Maximum Height', 'snn'),
            array($this, 'max_height_callback'),
            'snn-webp-optimization',
            'snn_webp_general'
        );
        
        add_settings_field(
            'enable_preload',
            __('Enable Critical Image Preloading', 'snn'),
            array($this, 'enable_preload_callback'),
            'snn-webp-optimization',
            'snn_webp_general'
        );
        
        add_settings_field(
            'enable_lazy_loading',
            __('Enable Lazy Loading', 'snn'),
            array($this, 'enable_lazy_loading_callback'),
            'snn-webp-optimization',
            'snn_webp_general'
        );
        
        add_settings_field(
            'enable_placeholder',
            __('Enable Placeholder Images', 'snn'),
            array($this, 'enable_placeholder_callback'),
            'snn-webp-optimization',
            'snn_webp_general'
        );
        
        add_settings_field(
            'critical_images',
            __('Critical Images URLs', 'snn'),
            array($this, 'critical_images_callback'),
            'snn-webp-optimization',
            'snn_webp_general'
        );
        
        add_settings_field(
            'batch_size',
            __('Batch Size for Conversion', 'snn'),
            array($this, 'batch_size_callback'),
            'snn-webp-optimization',
            'snn_webp_general'
        );
    }
    
    /**
     * Section callbacks
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure WebP image optimization settings for better Core Web Vitals.', 'snn') . '</p>';
    }
    
    /**
     * Field callbacks
     */
    public function enable_webp_callback() {
        $enabled = isset($this->options['enable_webp']) ? $this->options['enable_webp'] : true;
        echo '<input type="checkbox" name="snn_webp_options[enable_webp]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Automatically convert uploaded images to WebP format.', 'snn') . '</p>';
    }
    
    public function webp_quality_callback() {
        $quality = $this->options['webp_quality'] ?? 85;
        echo '<input type="number" name="snn_webp_options[webp_quality]" value="' . esc_attr($quality) . '" min="1" max="100" />';
        echo '<p class="description">' . __('WebP quality (1-100). Higher values mean better quality but larger file sizes.', 'snn') . '</p>';
    }
    
    public function max_width_callback() {
        $width = $this->options['max_width'] ?? 1920;
        echo '<input type="number" name="snn_webp_options[max_width]" value="' . esc_attr($width) . '" min="100" max="4000" />';
        echo '<p class="description">' . __('Maximum width for images (pixels).', 'snn') . '</p>';
    }
    
    public function max_height_callback() {
        $height = $this->options['max_height'] ?? 1080;
        echo '<input type="number" name="snn_webp_options[max_height]" value="' . esc_attr($height) . '" min="100" max="4000" />';
        echo '<p class="description">' . __('Maximum height for images (pixels).', 'snn') . '</p>';
    }
    
    public function enable_preload_callback() {
        $enabled = isset($this->options['enable_preload']) ? $this->options['enable_preload'] : true;
        echo '<input type="checkbox" name="snn_webp_options[enable_preload]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Preload critical images for faster loading.', 'snn') . '</p>';
    }
    
    public function enable_lazy_loading_callback() {
        $enabled = isset($this->options['enable_lazy_loading']) ? $this->options['enable_lazy_loading'] : true;
        echo '<input type="checkbox" name="snn_webp_options[enable_lazy_loading]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Enable lazy loading for non-critical images.', 'snn') . '</p>';
    }
    
    public function enable_placeholder_callback() {
        $enabled = isset($this->options['enable_placeholder']) ? $this->options['enable_placeholder'] : true;
        echo '<input type="checkbox" name="snn_webp_options[enable_placeholder]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Show placeholder images while loading.', 'snn') . '</p>';
    }
    
    public function critical_images_callback() {
        $images = $this->options['critical_images'] ?? '';
        echo '<textarea name="snn_webp_options[critical_images]" rows="5" cols="50" class="large-text">' . esc_textarea($images) . '</textarea>';
        echo '<p class="description">' . __('Add critical image URLs to preload (one per line).', 'snn') . '</p>';
    }
    
    public function batch_size_callback() {
        $batch_size = $this->options['batch_size'] ?? 50;
        echo '<input type="number" name="snn_webp_options[batch_size]" value="' . esc_attr($batch_size) . '" min="10" max="200" />';
        echo '<p class="description">' . __('Number of images to process per batch (10-200). Lower values for better performance with thousands of images.', 'snn') . '</p>';
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        $sanitized['enable_webp'] = isset($input['enable_webp']) ? 1 : 0;
        $sanitized['webp_quality'] = intval($input['webp_quality'] ?? 85);
        $sanitized['max_width'] = intval($input['max_width'] ?? 1920);
        $sanitized['max_height'] = intval($input['max_height'] ?? 1080);
        $sanitized['enable_preload'] = isset($input['enable_preload']) ? 1 : 0;
        $sanitized['enable_lazy_loading'] = isset($input['enable_lazy_loading']) ? 1 : 0;
        $sanitized['enable_placeholder'] = isset($input['enable_placeholder']) ? 1 : 0;
        $sanitized['critical_images'] = sanitize_textarea_field($input['critical_images'] ?? '');
        $sanitized['batch_size'] = intval($input['batch_size'] ?? 50);
        
        return $sanitized;
    }
    
    /**
     * Convert images via AJAX (Batch Processing)
     */
    public function convert_images_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'snn_webp_nonce')) {
            wp_die('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $batch_size = intval($_POST['batch_size'] ?? 50);
        $offset = intval($_POST['offset'] ?? 0);
        $total_processed = intval($_POST['total_processed'] ?? 0);
        $total_converted = intval($_POST['total_converted'] ?? 0);
        $total_errors = intval($_POST['total_errors'] ?? 0);
        
        // Get images for this batch
        $images = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => $batch_size,
            'offset' => $offset,
            'post_status' => 'inherit',
            'orderby' => 'ID',
            'order' => 'ASC'
        ));
        
        $batch_converted = 0;
        $batch_errors = 0;
        
        foreach ($images as $image) {
            $file_path = get_attached_file($image->ID);
            if ($file_path && file_exists($file_path)) {
                $result = $this->convert_image_to_webp($file_path);
                if ($result) {
                    $batch_converted++;
                } else {
                    $batch_errors++;
                }
            }
        }
        
        $total_processed += count($images);
        $total_converted += $batch_converted;
        $total_errors += $batch_errors;
        
        // Check if there are more images to process
        $remaining_images = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => 1,
            'offset' => $offset + $batch_size,
            'post_status' => 'inherit'
        ));
        
        $has_more = !empty($remaining_images);
        
        wp_send_json_success(array(
            'message' => $has_more ? 'Batch processed' : 'All images processed',
            'batch_converted' => $batch_converted,
            'batch_errors' => $batch_errors,
            'batch_processed' => count($images),
            'total_converted' => $total_converted,
            'total_errors' => $total_errors,
            'total_processed' => $total_processed,
            'has_more' => $has_more,
            'next_offset' => $offset + $batch_size,
            'progress_percent' => $this->calculate_progress_percent($total_processed)
        ));
    }
    
    /**
     * Calculate progress percentage
     */
    private function calculate_progress_percent($processed) {
        $total_images = wp_count_attachments('image');
        $total_count = ($total_images->inherit ?? 0) + ($total_images->private ?? 0) + ($total_images->trash ?? 0);
        
        if ($total_count == 0) {
            return 100;
        }
        
        return round(($processed / $total_count) * 100, 1);
    }
    
    /**
     * Start background conversion
     */
    public function start_background_conversion() {
        if (!wp_verify_nonce($_POST['nonce'], 'snn_webp_nonce')) {
            wp_die('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Clear any existing conversion status
        delete_option('snn_webp_conversion_status');
        
        // Schedule the first batch
        wp_schedule_single_event(time(), 'snn_webp_background_conversion');
        
        wp_send_json_success(array(
            'message' => 'Background conversion started',
            'status' => 'running'
        ));
    }
    
    /**
     * Get conversion status
     */
    public function get_conversion_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'snn_webp_nonce')) {
            wp_die('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $status = get_option('snn_webp_conversion_status', array(
            'status' => 'idle',
            'total_processed' => 0,
            'total_converted' => 0,
            'total_errors' => 0,
            'progress_percent' => 0,
            'current_batch' => 0,
            'total_batches' => 0
        ));
        
        wp_send_json_success($status);
    }
    
    /**
     * Schedule background conversion
     */
    public function schedule_background_conversion() {
        // Only schedule if not already scheduled
        if (!wp_next_scheduled('snn_webp_background_conversion')) {
            // Schedule to run every 5 minutes during conversion
            wp_schedule_event(time(), 'snn_webp_background_conversion_interval', 'snn_webp_background_conversion');
        }
    }
    
    /**
     * Process background conversion
     */
    public function process_background_conversion() {
        $status = get_option('snn_webp_conversion_status', array(
            'status' => 'idle',
            'total_processed' => 0,
            'total_converted' => 0,
            'total_errors' => 0,
            'current_batch' => 0,
            'total_batches' => 0,
            'last_processed_id' => 0
        ));
        
        if ($status['status'] !== 'running') {
            return;
        }
        
        $batch_size = $this->options['batch_size'] ?? 50;
        
        // Get images for this batch
        $images = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => $batch_size,
            'post_status' => 'inherit',
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_snn_webp_converted',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        if (empty($images)) {
            // No more images to process
            $status['status'] = 'completed';
            $status['progress_percent'] = 100;
            update_option('snn_webp_conversion_status', $status);
            
            // Clear the scheduled event
            wp_clear_scheduled_hook('snn_webp_background_conversion');
            return;
        }
        
        $batch_converted = 0;
        $batch_errors = 0;
        
        foreach ($images as $image) {
            $file_path = get_attached_file($image->ID);
            if ($file_path && file_exists($file_path)) {
                $result = $this->convert_image_to_webp($file_path);
                if ($result) {
                    $batch_converted++;
                    // Mark as converted
                    update_post_meta($image->ID, '_snn_webp_converted', time());
                } else {
                    $batch_errors++;
                }
            }
        }
        
        // Update status
        $status['total_processed'] += count($images);
        $status['total_converted'] += $batch_converted;
        $status['total_errors'] += $batch_errors;
        $status['current_batch']++;
        $status['progress_percent'] = $this->calculate_progress_percent($status['total_processed']);
        
        update_option('snn_webp_conversion_status', $status);
        
        // Schedule next batch if there are more images
        $remaining_images = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => 1,
            'post_status' => 'inherit',
            'meta_query' => array(
                array(
                    'key' => '_snn_webp_converted',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        if (!empty($remaining_images)) {
            // Schedule next batch in 30 seconds
            wp_schedule_single_event(time() + 30, 'snn_webp_background_conversion');
        } else {
            // No more images, mark as completed
            $status['status'] = 'completed';
            $status['progress_percent'] = 100;
            update_option('snn_webp_conversion_status', $status);
        }
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules) {
        $schedules['snn_webp_background_conversion_interval'] = array(
            'interval' => 300, // 5 minutes
            'display' => __('WebP Background Conversion', 'snn')
        );
        
        return $schedules;
    }
    
    /**
     * Optimize images via AJAX
     */
    public function optimize_images_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'snn_webp_nonce')) {
            wp_die('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // This would integrate with external optimization services
        // For now, just return success
        wp_send_json_success(array(
            'message' => 'Image optimization completed',
            'optimized' => 0,
            'saved_space' => '0 MB'
        ));
    }
}

// Initialize the WebP image optimizer
new SNN_WebP_Image_Optimizer();
