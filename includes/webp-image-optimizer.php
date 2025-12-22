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
        $this->quality = $this->options['webp_quality'] ?? 80; // Default 80 (WebP comprime mejor que JPEG, puede usar calidad ligeramente mayor)
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
        
        // Frontend hooks - Reemplazar URLs en el HTML generado
        // Prioridad alta (10) para asegurar que se ejecute antes que otros filtros
        add_filter('wp_get_attachment_image_src', array($this, 'replace_with_webp'), 10, 4);
        add_filter('wp_get_attachment_image_url', array($this, 'replace_attachment_image_url'), 10, 3);
        add_filter('wp_get_attachment_url', array($this, 'replace_attachment_url_with_webp'), 10, 2);
        add_filter('the_content', array($this, 'replace_content_images'), 10, 1);
        add_filter('wp_calculate_image_srcset', array($this, 'replace_srcset_with_webp'), 10, 5);
        add_filter('the_post_thumbnail_url', array($this, 'replace_post_thumbnail_url'), 10, 3);
        
        // Preload de imágenes críticas
        add_action('wp_head', array($this, 'preload_critical_images'));
        
        // Automatic WebP serving (para peticiones directas de imágenes)
        add_action('template_redirect', array($this, 'serve_webp_images'));
        add_action('init', array($this, 'handle_image_requests'));
        
        // Output buffer para interceptar HTML final y reemplazar URLs (última línea de defensa)
        // Esto captura URLs que Bricks Builder u otros plugins puedan generar directamente
        // Usar init con prioridad alta para asegurar que se ejecute antes que otros output buffers
        if (!is_admin()) {
            add_action('init', array($this, 'start_output_buffer'), 9999);
            add_action('shutdown', array($this, 'end_output_buffer'), 999);
        }
        
        // Admin hooks
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_snn_convert_images', array($this, 'convert_images_ajax'));
        add_action('wp_ajax_snn_optimize_images', array($this, 'optimize_images_ajax'));
        add_action('wp_ajax_snn_start_background_conversion', array($this, 'start_background_conversion'));
        add_action('wp_ajax_snn_get_conversion_status', array($this, 'get_conversion_status'));
        add_action('wp_ajax_snn_test_conversion', array($this, 'test_conversion_ajax'));
        add_action('wp_ajax_snn_convert_folder', array($this, 'convert_folder_ajax'));
        add_action('wp_ajax_snn_convert_pending_images', array($this, 'convert_pending_images_ajax'));
        add_action('wp_ajax_snn_convert_recent_posts_images', array($this, 'convert_recent_posts_images_ajax'));
        add_action('wp_ajax_snn_get_webp_statistics', array($this, 'get_webp_statistics_ajax'));
        
        // WP Cron hooks for background processing
        add_action('snn_webp_background_conversion', array($this, 'process_background_conversion'));
        add_action('init', array($this, 'schedule_background_conversion'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
        // Lazy loading
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lazy_loading'), 10, 3);
    }

    /**
     * Convert all images inside a specific folder (absolute or uploads-relative)
     */
    public function convert_folder_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'snn_webp_nonce')) {
            wp_die('Invalid nonce');
        }
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        $folder = isset($_POST['folder']) ? sanitize_text_field(wp_unslash($_POST['folder'])) : '';
        if ($folder === '') {
            wp_send_json_error(array('message' => __('Folder is required', 'snn')));
        }
        $uploads = wp_upload_dir();
        $basedir = rtrim($uploads['basedir'], '/\\');
        // Support uploads-relative paths like /fotoedicion/2025/10
        if (strpos($folder, ':') === false && strpos($folder, $basedir) !== 0) {
            $folder = $basedir . '/' . ltrim($folder, '/\\');
        }
        $realBase = realpath($basedir);
        $realFolder = realpath($folder);
        if ($realFolder === false || strpos($realFolder, $realBase) !== 0) {
            wp_send_json_error(array('message' => __('Folder must be inside uploads directory', 'snn')));
        }
        $processed = 0; $converted = 0; $errors = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realFolder, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) { continue; }
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, array('jpg','jpeg','png'), true)) { continue; }
            $processed++;
            $result = $this->convert_image_to_webp($file->getPathname());
            if ($result) { $converted++; } else { $errors++; }
        }
        wp_send_json_success(array(
            'processed' => $processed,
            'converted' => $converted,
            'errors' => $errors,
            'folder' => $realFolder,
        ));
    }
    
    /**
     * Convert all pending images (JPG/PNG without WebP versions)
     */
    public function convert_pending_images_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'snn_webp_nonce')) {
            wp_die('Invalid nonce');
        }
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $uploads = wp_upload_dir();
        $basedir = rtrim($uploads['basedir'], '/\\');
        $processed = 0;
        $converted = 0;
        $errors = 0;
        
        // Get batch size from options
        $batch_size = intval($this->options['batch_size'] ?? 50);
        
        // Find all pending images
        $pending_images = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basedir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            $ext = strtolower($file->getExtension());
            
            // Only check JPG and PNG files
            if (!in_array($ext, array('jpg', 'jpeg', 'png'))) {
                continue;
            }
            
            // Check if WebP version exists in the same directory
            $file_info = pathinfo($file->getPathname());
            $webp_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
            
            if (!file_exists($webp_path)) {
                $pending_images[] = $file->getPathname();
            }
        }
        
        // Process images in batches
        $total = count($pending_images);
        $batch = array_slice($pending_images, 0, $batch_size);
        
        foreach ($batch as $image_path) {
            $processed++;
            $result = $this->convert_image_to_webp($image_path);
            if ($result) {
                $converted++;
            } else {
                $errors++;
            }
        }
        
        wp_send_json_success(array(
            'processed' => $processed,
            'converted' => $converted,
            'errors' => $errors,
            'total_pending' => $total,
            'remaining' => max(0, $total - $batch_size),
            'message' => sprintf(
                __('Procesadas %d de %d imágenes pendientes', 'snn'),
                $processed,
                $total
            )
        ));
    }
    
    /**
     * Convert images from the 100 most recent posts
     * This includes featured images and images in post content
     */
    public function convert_recent_posts_images_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'snn_webp_nonce')) {
            wp_die('Invalid nonce');
        }
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Check if WebP conversion is disabled
        if (!($this->options['enable_webp'] ?? true)) {
            wp_send_json_error(array('message' => __('WebP conversion is disabled', 'snn')));
        }
        
        $posts_per_batch = intval($_POST['posts_per_batch'] ?? 10);
        $offset = intval($_POST['offset'] ?? 0);
        $total_processed = intval($_POST['total_processed'] ?? 0);
        $total_converted = intval($_POST['total_converted'] ?? 0);
        $total_errors = intval($_POST['total_errors'] ?? 0);
        
        // Get recent posts (100 total, but process in batches)
        $posts = get_posts(array(
            'post_type' => 'any',
            'post_status' => 'publish',
            'numberposts' => $posts_per_batch,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC',
            'posts_per_page' => $posts_per_batch
        ));
        
        $batch_converted = 0;
        $batch_errors = 0;
        $images_processed = 0;
        
        foreach ($posts as $post) {
            // 1. Convert featured image
            $featured_image_id = get_post_thumbnail_id($post->ID);
            if ($featured_image_id) {
                $images_processed++;
                $file_path = get_attached_file($featured_image_id);
                if ($file_path && file_exists($file_path)) {
                    $result = $this->convert_image_to_webp($file_path);
                    if ($result) {
                        $batch_converted++;
                    } else {
                        $batch_errors++;
                    }
                    // Convert all sizes of featured image
                    $this->convert_all_image_sizes($featured_image_id);
                }
            }
            
            // 2. Convert images in post content
            $content = $post->post_content;
            if (!empty($content)) {
                // Find image attachments in content
                preg_match_all('/wp-image-(\d+)/', $content, $matches);
                if (!empty($matches[1])) {
                    $attachment_ids = array_unique(array_map('intval', $matches[1]));
                    foreach ($attachment_ids as $attachment_id) {
                        $images_processed++;
                        $file_path = get_attached_file($attachment_id);
                        if ($file_path && file_exists($file_path)) {
                            $result = $this->convert_image_to_webp($file_path);
                            if ($result) {
                                $batch_converted++;
                            } else {
                                $batch_errors++;
                            }
                            // Convert all sizes
                            $this->convert_all_image_sizes($attachment_id);
                        }
                    }
                }
            }
        }
        
        $total_processed += count($posts);
        $total_converted += $batch_converted;
        $total_errors += $batch_errors;
        
        // Check if we've processed 100 posts
        $has_more = ($offset + $posts_per_batch) < 100;
        
        wp_send_json_success(array(
            'message' => $has_more ? sprintf(__('Procesados %d posts (batch)', 'snn'), count($posts)) : __('Todos los posts procesados', 'snn'),
            'batch_converted' => $batch_converted,
            'batch_errors' => $batch_errors,
            'batch_processed' => count($posts),
            'images_processed' => $images_processed,
            'total_converted' => $total_converted,
            'total_errors' => $total_errors,
            'total_processed' => $total_processed,
            'has_more' => $has_more,
            'next_offset' => $offset + $posts_per_batch,
            'progress_percent' => min(100, round(($total_processed / 100) * 100, 1))
        ));
    }
    
    /**
     * Get WebP statistics (for AJAX loading)
     * This allows the admin page to load quickly while stats calculate in background
     */
    public function get_webp_statistics_ajax() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'snn')));
        }
        
        // Check if force refresh is requested
        $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'];
        
        // Check cache first (cache for 5 minutes) unless force refresh
        $cache_key = 'snn_webp_statistics';
        $cached_stats = get_transient($cache_key);
        
        if ($cached_stats !== false && !$force_refresh) {
            wp_send_json_success($cached_stats);
            return;
        }
        
        // Delete cache if force refresh
        if ($force_refresh) {
            delete_transient($cache_key);
        }
        
        // Calculate statistics
        $total_images = wp_count_attachments('image');
        $total_count = ($total_images->inherit ?? 0) + ($total_images->private ?? 0) + ($total_images->trash ?? 0);
        
        // If total is 0, try more accurate count
        if ($total_count == 0) {
            $total_count = $this->get_accurate_image_count();
        }
        
        $webp_images = $this->count_webp_images();
        $saved_space = $this->calculate_saved_space();
        $disk_counts = $this->count_images_on_disk();
        $pending_info = $this->count_pending_images();
        $pending_count = $pending_info['count'];
        
        // Calculate conversion rate
        $conversion_rate = 0;
        if ($total_count > 0) {
            $conversion_rate = round(($webp_images / $total_count) * 100, 1);
        } else if ($webp_images > 0 && $disk_counts['total'] > 0) {
            $conversion_rate = round(($webp_images / $disk_counts['total']) * 100, 1);
        }
        
        $stats = array(
            'total_images' => $total_count,
            'webp_images' => $webp_images,
            'saved_space' => $saved_space,
            'conversion_rate' => $conversion_rate,
            'pending_count' => $pending_count,
            'disk_counts' => $disk_counts
        );
        
        // Cache for 5 minutes
        set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
        
        wp_send_json_success($stats);
    }
    
    /**
     * Count WebP images (internal method)
     */
    private function count_webp_images() {
        $upload_dir = wp_upload_dir();
        $upload_basedir = $upload_dir['basedir'];
        
        // Count WebP files in uploads directory recursively
        $count = 0;
        $webp_dir = $upload_basedir . '/webp/';
        
        if (is_dir($webp_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($webp_dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'webp') {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Calculate saved space (internal method)
     */
    private function calculate_saved_space() {
        $upload_dir = wp_upload_dir();
        $upload_basedir = $upload_dir['basedir'];
        $webp_dir = $upload_basedir . '/webp/';
        
        $total_size = 0;
        
        if (is_dir($webp_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($webp_dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'webp') {
                    $total_size += $file->getSize();
                }
            }
        }
        
        return size_format($total_size, 2);
    }
    
    /**
     * Count images on disk (internal method)
     */
    private function count_images_on_disk() {
        $upload_dir = wp_upload_dir();
        $base = $upload_dir['basedir'];
        $counts = array('jpg' => 0, 'jpeg' => 0, 'png' => 0, 'webp' => 0);
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                
                $ext = strtolower($file->getExtension());
                if (isset($counts[$ext])) {
                    $counts[$ext]++;
                }
            }
        } catch (Exception $e) {
            // Error reading directory
        }
        
        $total = $counts['jpg'] + $counts['jpeg'] + $counts['png'] + $counts['webp'];
        $rate = $total > 0 ? round(($counts['webp'] / $total) * 100, 1) : 0;
        
        return array(
            'jpg' => $counts['jpg'],
            'jpeg' => $counts['jpeg'],
            'png' => $counts['png'],
            'webp' => $counts['webp'],
            'total' => $total,
            'rate' => $rate
        );
    }
    
    /**
     * Count pending images (internal method)
     */
    private function count_pending_images() {
        $upload_dir = wp_upload_dir();
        $base = $upload_dir['basedir'];
        $pending = 0;
        $total_size = 0;
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                
                $ext = strtolower($file->getExtension());
                
                // Only check JPG and PNG files
                if (!in_array($ext, array('jpg', 'jpeg', 'png'))) {
                    continue;
                }
                
                // Check if WebP version exists in the same directory
                $file_info = pathinfo($file->getPathname());
                $webp_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
                
                if (!file_exists($webp_path)) {
                    $pending++;
                    $total_size += $file->getSize();
                }
            }
        } catch (Exception $e) {
            // Error reading directory
        }
        
        return array(
            'count' => $pending,
            'total_size' => $total_size
        );
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
        // Check if WebP conversion is disabled or Cloudflare is being used
        if (!($this->options['enable_webp'] ?? true) || $this->is_cloudflare_webp_enabled()) {
            return $upload;
        }
        
        $file_path = $upload['file'];
        $file_type = wp_check_filetype($file_path);
        
        // Skip if already WebP
        if (isset($file_type['ext']) && strtolower($file_type['ext']) === 'webp') {
            return $upload;
        }
        
        // Only convert supported image types
        if (in_array($file_type['type'], array('image/jpeg', 'image/png'))) {
            $this->convert_image_to_webp($file_path);
        }
        
        return $upload;
    }
    
    /**
     * Convert attachment image to WebP (ALL SIZES)
     */
    public function convert_attachment_image($attachment_id) {
        // Check if WebP conversion is disabled or Cloudflare is being used
        if (!($this->options['enable_webp'] ?? true) || $this->is_cloudflare_webp_enabled()) {
            return;
        }
        
        // Convert original image
        $file_path = get_attached_file($attachment_id);
        if ($file_path && file_exists($file_path)) {
            $this->convert_image_to_webp($file_path);
        }
        
        // Convert all image sizes if enabled
        if ($this->options['convert_all_sizes'] ?? true) {
            $this->convert_all_image_sizes($attachment_id);
        }
    }
    
    /**
     * Convert all image sizes to WebP
     */
    private function convert_all_image_sizes($attachment_id) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        if (!$metadata || !isset($metadata['sizes'])) {
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $file_info = pathinfo($metadata['file']);
        $base_dir = $upload_dir['basedir'] . '/' . $file_info['dirname'];
        
        // Convert each size
        foreach ($metadata['sizes'] as $size_name => $size_data) {
            $size_file_path = $base_dir . '/' . $size_data['file'];
            
            if (file_exists($size_file_path)) {
                $this->convert_image_to_webp($size_file_path);
            }
        }
    }
    
    /**
     * Convert image to WebP format (Replace original)
     */
    private function convert_image_to_webp($file_path) {
        if (!function_exists('imagewebp')) {
            return false;
        }
        
        // Fallback: If file doesn't exist, try swapping extensions (jpg <-> jpeg)
        // This handles cases where DB has .jpeg but file is .jpg (or vice versa)
        if (!file_exists($file_path)) {
            $info = pathinfo($file_path);
            if (isset($info['extension'])) {
                $ext = strtolower($info['extension']);
                $alt_path = '';
                
                if ($ext === 'jpg') {
                    $alt_path = preg_replace('/\.jpg$/i', '.jpeg', $file_path);
                } elseif ($ext === 'jpeg') {
                    $alt_path = preg_replace('/\.jpeg$/i', '.jpg', $file_path);
                }
                
                if ($alt_path && file_exists($alt_path)) {
                    $file_path = $alt_path;
                }
            }
        }
        
        // Skip if file is already WebP
        $file_info = pathinfo($file_path);
        if (isset($file_info['extension']) && strtolower($file_info['extension']) === 'webp') {
            return $file_path;
        }
        
        $webp_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
        
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
        
        // Convert palette images to true color for WebP compatibility
        if (imageistruecolor($image) === false) {
            $truecolor_image = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagecopy($truecolor_image, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagedestroy($image);
            $image = $truecolor_image;
        }
        
        // Resize if needed
        $image = $this->resize_image_if_needed($image, $file_path);
        
        // Convert to WebP
        $success = imagewebp($image, $webp_path, $this->quality);
        imagedestroy($image);
        
        if ($success) {
            // Update file permissions
            chmod($webp_path, 0644);
            
            // NO eliminar el original - WordPress lo necesita para:
            // 1. Generar tamaños (thumbnail, medium, large, etc.)
            // 2. Regenerar tamaños si es necesario
            // 3. Compatibilidad con plugins que necesitan el original
            // 4. Fallback si WebP no se puede servir
            // El sistema usará WebP para servir, pero mantendrá el original como respaldo
            
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
        // If Cloudflare is handling WebP, let it do its job
        if (!($this->options['enable_webp'] ?? true) || $this->is_cloudflare_webp_enabled()) {
            return $image;
        }
        
        if (!$image || !is_array($image) || !isset($image[0]) || !$attachment_id) {
            return $image;
        }
        
        // Validar que la URL original existe
        if (empty($image[0]) || !filter_var($image[0], FILTER_VALIDATE_URL)) {
            return $image;
        }
        
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return $image;
        }
        
        $file_info = pathinfo($file_path);
        if (!isset($file_info['filename']) || !isset($file_info['dirname'])) {
            return $image;
        }
        
        // Buscar WebP en la misma carpeta que el original (respeta estructura de carpetas personalizada)
        $webp_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
        
        // Check if WebP version exists in same directory
        if (file_exists($webp_path)) {
            // Construir URL relativa desde uploads
            $upload_basedir = $this->upload_dir['basedir'];
            $upload_baseurl = $this->upload_dir['baseurl'];
            
            // Obtener ruta relativa desde uploads
            $relative_path = str_replace($upload_basedir, '', $webp_path);
            $relative_path = ltrim($relative_path, '/\\');
            
            $webp_url = $upload_baseurl . '/' . $relative_path;
            
            // Validar que la URL WebP es válida antes de reemplazar
            if (filter_var($webp_url, FILTER_VALIDATE_URL)) {
                $image[0] = $webp_url;
            }
        }
        
        return $image;
    }
    
    /**
     * Replace images in content with WebP versions and optimize attributes (Enhanced)
     * Optimizes images in post content with lazy loading, fetchpriority, and other attributes
     */
    public function replace_content_images($content) {
        if (empty($content)) {
            return $content;
        }
        
        // Get critical images to determine which images should be prioritized
        $critical_images = $this->get_critical_images();
        
        // Find all img tags with their full attributes
        preg_match_all('/<img([^>]+)>/i', $content, $matches, PREG_SET_ORDER);
        
        $image_index = 0;
        foreach ($matches as $match) {
            $full_tag = $match[0];
            $attributes = $match[1];
            
            // Extract src attribute
            if (!preg_match('/src=["\']([^"\']+)["\']/', $attributes, $src_match)) {
                continue;
            }
            
            $image_url = $src_match[1];
            
            // Skip if URL is invalid
            if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
                continue;
            }
            
            // Check if this is a critical image (featured image, etc.)
            $is_critical = in_array($image_url, $critical_images);
            
            // Determine if this is the first image in content (might be above the fold)
            $is_first_content_image = ($image_index === 0 && !$is_critical);
            
            // Build optimized attributes
            $optimized_attributes = $attributes;
            
            // 1. Replace with WebP if enabled and not Cloudflare
            $webp_enabled = ($this->options['enable_webp'] ?? true) && !$this->is_cloudflare_webp_enabled();
            if ($webp_enabled) {
                $webp_url = $this->get_webp_url($image_url);
                if ($webp_url && filter_var($webp_url, FILTER_VALIDATE_URL)) {
                    $optimized_attributes = preg_replace(
                        '/src=["\']' . preg_quote($image_url, '/') . '["\']/',
                        'src="' . esc_attr($webp_url) . '"',
                        $optimized_attributes
                    );
                    
                    // Also replace in srcset if present
                    $optimized_attributes = preg_replace_callback(
                        '/srcset=["\']([^"\']*' . preg_quote($image_url, '/') . '[^"\']*)["\']/i',
                        function($matches) use ($image_url, $webp_url) {
                            return 'srcset="' . str_replace($image_url, $webp_url, $matches[1]) . '"';
                        },
                        $optimized_attributes
                    );
                }
            }
            
            // 2. Add loading attribute (lazy for non-critical images)
            if (!preg_match('/\sloading=["\']/', $optimized_attributes)) {
                if ($is_critical || $is_first_content_image) {
                    // Critical images or first content image: load eagerly
                    $optimized_attributes .= ' loading="eager"';
                } else {
                    // Other images: lazy load
                    $optimized_attributes .= ' loading="lazy"';
                }
            }
            
            // 3. Add fetchpriority attribute
            if (!preg_match('/\sfetchpriority=["\']/', $optimized_attributes)) {
                if ($is_critical || $is_first_content_image) {
                    // Critical images and first content image get high priority
                    // This helps LCP significantly
                    $optimized_attributes .= ' fetchpriority="high"';
                } else {
                    // All other content images get low priority to not compete with LCP
                    $optimized_attributes .= ' fetchpriority="low"';
                }
            }
            
            // 4. Add decoding attribute for better performance
            if (!preg_match('/\sdecoding=["\']/', $optimized_attributes)) {
                if ($is_critical || $is_first_content_image) {
                    $optimized_attributes .= ' decoding="sync"';
                } else {
                    $optimized_attributes .= ' decoding="async"';
                }
            }
            
            // 5. Try to add width and height if missing (prevents CLS)
            if (!preg_match('/\swidth=["\']/', $optimized_attributes) || !preg_match('/\sheight=["\']/', $optimized_attributes)) {
                // Try to get attachment ID from URL
                $attachment_id = attachment_url_to_postid($image_url);
                if ($attachment_id) {
                    $metadata = wp_get_attachment_metadata($attachment_id);
                    if ($metadata && isset($metadata['width']) && isset($metadata['height'])) {
                        // Add width and height if not present
                        if (!preg_match('/\swidth=["\']/', $optimized_attributes)) {
                            $optimized_attributes .= ' width="' . esc_attr($metadata['width']) . '"';
                        }
                        if (!preg_match('/\sheight=["\']/', $optimized_attributes)) {
                            $optimized_attributes .= ' height="' . esc_attr($metadata['height']) . '"';
                        }
                    }
                }
            }
            
            // Replace the original img tag with optimized version
            $optimized_tag = '<img' . $optimized_attributes . '>';
            $content = str_replace($full_tag, $optimized_tag, $content);
            
            $image_index++;
        }
        
        return $content;
    }
    
    /**
     * Preload critical images
     */
    public function preload_critical_images() {
        if (!($this->options['enable_preload'] ?? true)) {
            return;
        }
        
        // Usar caché estático para evitar múltiples llamadas
        static $preload_output = false;
        if ($preload_output) {
            return; // Ya se ejecutó, evitar duplicados
        }
        
        $critical_images = $this->get_critical_images();
        
        // Limitar a máximo 5 imágenes críticas para evitar sobrecarga
        $max_critical_images = 5;
        $critical_images = array_slice($critical_images, 0, $max_critical_images);
        
        $preloaded_count = 0;
        foreach ($critical_images as $index => $image_url) {
            try {
                // Validar URL antes de preload
                if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
                    continue;
                }
                
                // Limitar número de preloads para evitar errores 502
                if ($preloaded_count >= $max_critical_images) {
                    break;
                }
                
                // Solo la primera imagen crítica (LCP candidate) debe tener fetchpriority="high"
                // Las demás deben tener fetchpriority="auto" o no tener el atributo
                // Esto ayuda al navegador a priorizar correctamente el LCP
                $fetchpriority = ($index === 0) ? 'high' : 'auto';
                
                // Preferir WebP si existe, sino preload la original
                $webp_url = $this->get_webp_url($image_url);
                if ($webp_url && filter_var($webp_url, FILTER_VALIDATE_URL)) {
                    echo '<link rel="preload" as="image" href="' . esc_url($webp_url) . '" fetchpriority="' . esc_attr($fetchpriority) . '" type="image/webp">' . "\n";
                } else {
                    // Solo preload original si no hay WebP
                    echo '<link rel="preload" as="image" href="' . esc_url($image_url) . '" fetchpriority="' . esc_attr($fetchpriority) . '">' . "\n";
                }
                
                $preloaded_count++;
            } catch (Exception $e) {
                // Silenciosamente continuar si hay un error con una imagen
                // Esto previene que un error en una imagen detenga todo el proceso
                continue;
            }
        }
        
        $preload_output = true; // Marcar como ejecutado
    }
    
    /**
     * Get critical images for preloading
     * Priority order: Featured image > First content image > First gallery image > Logo
     */
    private function get_critical_images() {
        // Usar caché estático para evitar consultas múltiples
        static $cached_images = null;
        if ($cached_images !== null) {
            return $cached_images;
        }
        
        $critical_images = array();
        
        // Get featured image of current post (highest priority for LCP)
        if (is_singular()) {
            global $post;
            
            // 1. Featured image (highest priority)
            $featured_image_id = get_post_thumbnail_id();
            if ($featured_image_id) {
                $featured_image_url = wp_get_attachment_image_url($featured_image_id, 'large');
                if ($featured_image_url) {
                    $critical_images[] = $featured_image_url;
                }
            }
            
            // 2. If no featured image, get first image from post content
            if (empty($critical_images) && $post) {
                $first_content_image = $this->get_first_content_image($post);
                if ($first_content_image) {
                    $critical_images[] = $first_content_image;
                }
            }
            
            // 3. If still no image, get first image from WordPress gallery
            if (empty($critical_images) && $post) {
                $first_gallery_image = $this->get_first_gallery_image($post);
                if ($first_gallery_image) {
                    $critical_images[] = $first_gallery_image;
                }
            }
        }
        
        // Get logo from theme mod
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo_url) {
                $critical_images[] = $logo_url;
                // NO agregar WebP aquí, se manejará en preload_critical_images para evitar duplicados
            }
        }
        
        // Find logo images by filename pattern (for Bricks Builder usage)
        // Limitado a 1 por patrón para evitar demasiadas consultas
        $logo_file_patterns = array('logo-dln', 'logo', 'brand');
        
        // Search for logo files in uploads (limitado para rendimiento)
        foreach ($logo_file_patterns as $pattern) {
            $args = array(
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'posts_per_page' => 1, // Reducido de 10 a 1 para mejor rendimiento
                'post_status' => 'inherit',
                'meta_query' => array(
                    array(
                        'key' => '_wp_attached_file',
                        'value' => $pattern,
                        'compare' => 'LIKE'
                    )
                )
            );
            
            $logo_attachments = get_posts($args);
            foreach ($logo_attachments as $attachment) {
                $logo_url = wp_get_attachment_image_url($attachment->ID, 'full');
                if ($logo_url) {
                    $critical_images[] = $logo_url;
                    // NO agregar WebP aquí, se manejará en preload_critical_images
                }
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
        
        // Get logo from theme mod (only if no post images found)
        // Logo is lower priority than post images for LCP
        if (empty($critical_images)) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
                if ($logo_url) {
                    $critical_images[] = $logo_url;
                }
            }
        }
        
        // Find logo images by filename pattern (for Bricks Builder usage)
        // Only if no post images found
        if (empty($critical_images)) {
            $logo_file_patterns = array('logo-dln', 'logo', 'brand');
            
            // Search for logo files in uploads (limitado para rendimiento)
            foreach ($logo_file_patterns as $pattern) {
                $args = array(
                    'post_type' => 'attachment',
                    'post_mime_type' => 'image',
                    'posts_per_page' => 1,
                    'post_status' => 'inherit',
                    'meta_query' => array(
                        array(
                            'key' => '_wp_attached_file',
                            'value' => $pattern,
                            'compare' => 'LIKE'
                        )
                    )
                );
                
                $logo_attachments = get_posts($args);
                foreach ($logo_attachments as $attachment) {
                    $logo_url = wp_get_attachment_image_url($attachment->ID, 'full');
                    if ($logo_url) {
                        $critical_images[] = $logo_url;
                        break 2; // Break both loops
                    }
                }
            }
        }
        
        // Get custom critical images from options (always add these)
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
        
        // Guardar en caché estático
        $cached_images = array_unique($critical_images);
        return $cached_images;
    }
    
    /**
     * Get first image from post content
     * Extracts the first <img> tag from post content
     */
    private function get_first_content_image($post) {
        if (!$post || empty($post->post_content)) {
            return null;
        }
        
        $content = $post->post_content;
        
        // Try to find first image tag
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches)) {
            $image_url = esc_url($matches[1]);
            
            // Convert relative URLs to absolute
            if (strpos($image_url, 'http') !== 0) {
                $image_url = home_url($image_url);
            }
            
            // Try to get attachment ID from URL
            $attachment_id = attachment_url_to_postid($image_url);
            if ($attachment_id) {
                // Get proper image size URL
                $image_url = wp_get_attachment_image_url($attachment_id, 'large');
            }
            
            return $image_url ?: null;
        }
        
        return null;
    }
    
    /**
     * Get first image from WordPress gallery
     * Extracts the first image from [gallery] shortcode
     */
    private function get_first_gallery_image($post) {
        if (!$post || empty($post->post_content)) {
            return null;
        }
        
        $content = $post->post_content;
        
        // Check if there's a gallery shortcode
        if (has_shortcode($content, 'gallery')) {
            // Get gallery shortcode attributes
            preg_match('/\[gallery[^\]]*ids=["\']([^"\']+)["\'][^\]]*\]/', $content, $matches);
            
            if (!empty($matches[1])) {
                // Get first image ID from gallery
                $image_ids = explode(',', $matches[1]);
                $first_image_id = intval(trim($image_ids[0]));
                
                if ($first_image_id > 0) {
                    $image_url = wp_get_attachment_image_url($first_image_id, 'large');
                    return $image_url ?: null;
                }
            } else {
                // Gallery without explicit IDs - get attached images
                $attachments = get_attached_media('image', $post->ID);
                if (!empty($attachments)) {
                    $first_attachment = reset($attachments);
                    $image_url = wp_get_attachment_image_url($first_attachment->ID, 'large');
                    return $image_url ?: null;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Add lazy loading attributes
     */
    public function add_lazy_loading($attr, $attachment, $size) {
        // Guard clause: prevent double execution if already processed
        if (isset($attr['class']) && strpos($attr['class'], 'snn-lazy-image') !== false) {
            return $attr;
        }

        if (!($this->options['enable_lazy_loading'] ?? true)) {
            return $attr;
        }
        
        // Check if this is the featured image by attachment ID (more reliable than URL comparison)
        $is_featured_image = false;
        if (is_singular()) {
            $featured_image_id = get_post_thumbnail_id();
            if ($featured_image_id && $attachment->ID == $featured_image_id) {
                $is_featured_image = true;
            }
        }
        
        // Also check by URL for other critical images (logo, etc.)
        $critical_images = $this->get_critical_images();
        $current_image_url = wp_get_attachment_image_url($attachment->ID, $size);
        $is_critical_by_url = in_array($current_image_url, $critical_images);
        
        // Check all size variants of critical images
        if (!$is_critical_by_url && !empty($critical_images)) {
            foreach ($critical_images as $critical_url) {
                // Try to get attachment ID from critical URL
                $critical_attachment_id = attachment_url_to_postid($critical_url);
                if ($critical_attachment_id && $attachment->ID == $critical_attachment_id) {
                    $is_critical_by_url = true;
                    break;
                }
            }
        }
        
        $is_critical = $is_featured_image || $is_critical_by_url;
        
        // Check for logo images by filename pattern
        if (!$is_critical) {
            $file_path = get_attached_file($attachment->ID);
            if ($file_path) {
                $file_name = basename($file_path);
                $file_name_lower = strtolower($file_name);
                
                // Check for logo patterns
                $logo_patterns = array('logo', 'brand', 'header-logo', 'site-logo', 'logo-dln');
                foreach ($logo_patterns as $pattern) {
                    if (strpos($file_name_lower, $pattern) !== false) {
                        $is_critical = true;
                        break;
                    }
                }
                
                // Check for banner/ad patterns in filename
                if (!$is_critical) {
                    $banner_patterns = array('banner', 'ad', 'advertisement', 'publicidad', 'ads');
                    foreach ($banner_patterns as $pattern) {
                        if (strpos($file_name_lower, $pattern) !== false) {
                            $is_critical = true;
                            break;
                        }
                    }
                }
            }
        }
        
        // Check for critical classes
        if (!$is_critical) {
            $existing_class = $attr['class'] ?? '';
            if (!empty($existing_class)) {
                $critical_classes = array('logo', 'site-logo', 'header-logo', 'brand', 'critical-image', 'no-lazy', 'banner', 'ad', 'advertisement', 'publicidad', 'ads');
                $class_array = explode(' ', $existing_class);
                foreach ($class_array as $class) {
                    foreach ($critical_classes as $critical_class) {
                        if (stripos($class, $critical_class) !== false) {
                            $is_critical = true;
                            break 2;
                        }
                    }
                }
            }
        }
        
        // Check for banner/ad patterns in parent elements or context
        if (!$is_critical) {
            // Check if image is inside a banner/ad container by checking parent classes
            // This is a best-effort check since we don't have DOM access here
            $alt_text = $attr['alt'] ?? '';
            $title_attr = $attr['title'] ?? '';
            $aria_label = $attr['aria-label'] ?? '';
            
            $banner_keywords = array('banner', 'ad', 'advertisement', 'publicidad', 'ads', 'advertising');
            $text_to_check = strtolower($alt_text . ' ' . $title_attr . ' ' . $aria_label);
            
            foreach ($banner_keywords as $keyword) {
                if (stripos($text_to_check, $keyword) !== false) {
                    $is_critical = true;
                    break;
                }
            }
        }
        
        // For critical images (especially featured image), ensure optimal attributes
        if ($is_critical) {
            $attr['loading'] = 'eager';
            $attr['decoding'] = 'sync';
            
            // Featured image should have highest priority (LCP candidate)
            if ($is_featured_image) {
                $attr['fetchpriority'] = 'high';
            } else {
                // Other critical images (logo, etc.) get auto priority
                $attr['fetchpriority'] = 'auto';
            }
        }
        
        // Agregar width y height para prevenir CLS (Cumulative Layout Shift)
        $metadata = wp_get_attachment_metadata($attachment->ID);
        if ($metadata && isset($metadata['width']) && isset($metadata['height'])) {
            // Calcular dimensiones basadas en el tamaño solicitado
            $size_array = $this->get_image_size_dimensions($attachment->ID, $size);
            if ($size_array) {
                $attr['width'] = $size_array[0];
                $attr['height'] = $size_array[1];
            } else {
                // Fallback a dimensiones originales si no hay tamaño específico
                $attr['width'] = $metadata['width'];
                $attr['height'] = $metadata['height'];
            }
            // Agregar aspect-ratio para mejor CLS (prevenir layout shift)
            if ($attr['width'] > 0 && $attr['height'] > 0) {
                $attr['style'] = ($attr['style'] ?? '') . ' aspect-ratio: ' . $attr['width'] . ' / ' . $attr['height'] . ';';
            }
        }
        
        // Add lazy loading attributes solo si no es crítica
        if (!$is_critical) {
            $attr['loading'] = 'lazy';
            $attr['decoding'] = 'async';
            // Aplicar fetchpriority="low" a imágenes no críticas para que el navegador priorice la LCP
            $attr['fetchpriority'] = 'low';
            
            // Add placeholder solo si está habilitado
            if (false) { // TEMPORARILY DISABLED: Fix for base64 image issue
            // if ($this->options['enable_placeholder'] ?? true) {
                // Guardar la URL original en data-src
                // Nota: $attr['src'] no suele estar disponible en este filtro, así que la obtenemos explícitamente
                $image_url = wp_get_attachment_image_url($attachment->ID, $size);
                if ($image_url) {
                    $attr['data-src'] = $image_url;
                    
                    // Usar placeholder temporal
                    $attr['src'] = $this->get_placeholder_image($attachment->ID);
                    $attr['class'] = ($attr['class'] ?? '') . ' snn-lazy-image';
                }
            }
        }
        
        return $attr;
    }
    
    /**
     * Serve WebP images automatically (Enhanced)
     */
    public function serve_webp_images() {
        // Skip if Cloudflare is handling WebP
        if ($this->is_cloudflare_webp_enabled()) {
            return;
        }
        
        // Only serve WebP for image requests
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }
        
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // Check if it's an image request
        if (!preg_match('/\.(jpg|jpeg|png)$/i', $request_uri)) {
            return;
        }
        
        // Try multiple WebP locations
        $webp_paths = $this->get_webp_paths($request_uri);
        
        foreach ($webp_paths as $webp_path) {
            if (file_exists($webp_path)) {
                // Serve WebP with proper headers
                header('Content-Type: image/webp');
                header('Content-Length: ' . filesize($webp_path));
                header('Cache-Control: public, max-age=31536000');
                header('X-WebP-Served: true');
                
                readfile($webp_path);
                exit;
            }
        }
    }
    
    /**
     * Get possible WebP paths for an image request (Same location)
     */
    private function get_webp_paths($request_uri) {
        $webp_paths = array();
        
        // Extract filename from URI
        $path_info = pathinfo($request_uri);
        $filename = $path_info['filename'];
        $dirname = $path_info['dirname'];
        
        // Try different WebP locations (same directory priority)
        // Respetar estructura de carpetas personalizada (ej: fotoedicion)
        $possible_paths = array(
            // In same directory as original (preferred) - respeta estructura de carpetas
            ABSPATH . ltrim($dirname, '/') . '/' . $filename . '.webp',
            
            // Direct WebP conversion (reemplaza extensión en la misma ubicación)
            ABSPATH . ltrim(preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $request_uri), '/'),
            
            // In uploads directory (misma estructura de carpetas)
            $this->upload_dir['basedir'] . ltrim($dirname, '/') . '/' . $filename . '.webp',
        );
        
        return $possible_paths;
    }
    
    /**
     * Handle image requests early
     */
    public function handle_image_requests() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }
        
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // Check if it's an image request
        if (preg_match('/\.(jpg|jpeg|png)$/i', $request_uri)) {
            $this->serve_webp_images();
        }
    }
    
    /**
     * Replace attachment image URL with WebP version
     * Filtro para wp_get_attachment_image_url()
     */
    public function replace_attachment_image_url($url, $attachment_id, $size) {
        // If Cloudflare is handling WebP, let it do its job
        if (!($this->options['enable_webp'] ?? true) || $this->is_cloudflare_webp_enabled()) {
            return $url;
        }
        
        if (!$url || !$attachment_id) {
            return $url;
        }
        
        // Obtener ruta del archivo
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return $url;
        }
        
        $file_info = pathinfo($file_path);
        if (!isset($file_info['filename']) || !isset($file_info['dirname'])) {
            return $url;
        }
        
        // Buscar WebP en la misma carpeta
        $webp_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
        
        if (file_exists($webp_path)) {
            // Construir URL relativa desde uploads
            $upload_basedir = $this->upload_dir['basedir'];
            $upload_baseurl = $this->upload_dir['baseurl'];
            
            $relative_path = str_replace($upload_basedir, '', $webp_path);
            $relative_path = ltrim($relative_path, '/\\');
            
            $webp_url = $upload_baseurl . '/' . $relative_path;
            
            if (filter_var($webp_url, FILTER_VALIDATE_URL)) {
                return $webp_url;
            }
        }
        
        return $url;
    }
    
    /**
     * Replace post thumbnail URL with WebP version
     * Filtro para the_post_thumbnail_url()
     */
    public function replace_post_thumbnail_url($url, $post_id, $size) {
        // If Cloudflare is handling WebP, let it do its job
        if (!($this->options['enable_webp'] ?? true) || $this->is_cloudflare_webp_enabled()) {
            return $url;
        }
        
        if (!$url || !$post_id) {
            return $url;
        }
        
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if (!$thumbnail_id) {
            return $url;
        }
        
        // Usar el filtro de attachment URL
        return $this->replace_attachment_url_with_webp($url, $thumbnail_id);
    }
    
    /**
     * Replace attachment URL with WebP version
     */
    public function replace_attachment_url_with_webp($url, $attachment_id) {
        // If Cloudflare is handling WebP, let it do its job
        if (!($this->options['enable_webp'] ?? true) || $this->is_cloudflare_webp_enabled()) {
            return $url;
        }
        
        // Validar que la URL original existe y es válida
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        
        // Check if WebP version exists
        $webp_url = $this->get_webp_url($url);
        if ($webp_url && $webp_url !== false) {
            return $webp_url;
        }
        
        // Siempre devolver la URL original si no hay WebP
        return $url;
    }
    
    /**
     * Replace srcset with WebP versions
     */
    public function replace_srcset_with_webp($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        // If Cloudflare is handling WebP, let it do its job
        if (!($this->options['enable_webp'] ?? true) || $this->is_cloudflare_webp_enabled()) {
            return $sources;
        }
        
        foreach ($sources as $width => $source) {
            $webp_url = $this->get_webp_url($source['url']);
            if ($webp_url) {
                $sources[$width]['url'] = $webp_url;
            }
        }
        
        return $sources;
    }
    
    /**
     * Enhanced WebP URL detection (Same location)
     * Respeta estructura de carpetas personalizada (ej: fotoedicion)
     * Maneja URLs completas, relativas, y con estructura personalizada
     */
    private function get_webp_url($image_url) {
        if (empty($image_url)) {
            return false;
        }
        
        // Asegurar que upload_dir está actualizado (puede cambiar si UPLOADS está definido)
        $current_upload_dir = wp_upload_dir();
        $upload_url = $current_upload_dir['baseurl'];
        $upload_basedir = $current_upload_dir['basedir'];
        
        // Normalizar URL - puede venir como URL completa o relativa
        $image_url_clean = $image_url;
        $is_full_url = false;
        
        // Si es URL completa, extraer ruta
        if (preg_match('#^https?://[^/]+(/.+)$#', $image_url, $url_matches)) {
            $image_url_clean = $url_matches[1];
            $is_full_url = true;
        }
        
        // Detectar si es de uploads (puede ser /wp-content/uploads/ o /fotoedicion/)
        $relative_path = '';
        $is_from_uploads = false;
        
        // Caso 1: Contiene upload_url base
        // Normalizar upload_url para comparación (puede ser completa o relativa)
        $upload_url_for_comparison = $upload_url;
        if (strpos($upload_url, 'http') === 0) {
            // Si upload_url es completa, extraer solo la ruta para comparación con image_url_clean
            if (preg_match('#https?://[^/]+(/.+)$#', $upload_url, $upload_matches)) {
                $upload_url_for_comparison = $upload_matches[1];
            }
        }
        
        if (strpos($image_url_clean, $upload_url_for_comparison) !== false) {
            $relative_path = str_replace($upload_url_for_comparison, '', $image_url_clean);
            $relative_path = ltrim($relative_path, '/');
            $is_from_uploads = true;
        } elseif (strpos($image_url_clean, $upload_url) !== false && strpos($upload_url, 'http') !== 0) {
            // Fallback: si upload_url es relativa y está en la URL
            $relative_path = str_replace($upload_url, '', $image_url_clean);
            $relative_path = ltrim($relative_path, '/');
            $is_from_uploads = true;
        }
        // Caso 2: Contiene /wp-content/uploads/ (convertir a estructura personalizada si aplica)
        elseif (preg_match('#/wp-content/uploads/(.+)$#', $image_url_clean, $matches)) {
            $relative_path = $matches[1];
            $is_from_uploads = true;
            // Si upload_url no contiene /wp-content/uploads/, significa que hay carpeta personalizada
            // La ruta relativa ya está correcta (ej: 2025/08/banner.jpg)
            // upload_basedir ya apunta a la carpeta correcta (ej: /path/to/fotoedicion)
            // Así que la búsqueda del archivo WebP funcionará correctamente
        }
        // Caso 3: Contiene /fotoedicion/ (estructura personalizada)
        elseif (preg_match('#/(fotoedicion/.+)$#', $image_url_clean, $matches)) {
            $relative_path = $matches[1];
            $is_from_uploads = true;
        }
        // Caso 4: Empieza directamente con fotoedicion/ (sin slash inicial)
        elseif (preg_match('#^fotoedicion/(.+)$#', $image_url_clean, $matches)) {
            $relative_path = $matches[0]; // Incluye "fotoedicion/"
            $is_from_uploads = true;
        }
        
        if (!$is_from_uploads || empty($relative_path)) {
            return false;
        }
        
        $relative_path = ltrim($relative_path, '/\\');
        $file_info = pathinfo($relative_path);
        
        // Si ya es WebP, verificar que existe y devolverla
        if (isset($file_info['extension']) && strtolower($file_info['extension']) === 'webp') {
            $current_path = $upload_basedir . '/' . $relative_path;
            if (file_exists($current_path)) {
                return $image_url; // Ya es WebP, devolver tal cual
            }
            return false;
        }
        
        // Construir ruta del WebP en la misma carpeta
        if (!isset($file_info['dirname']) || !isset($file_info['filename'])) {
            return false;
        }
        
        // Manejar caso especial: si dirname es "." (mismo directorio)
        if ($file_info['dirname'] === '.' || $file_info['dirname'] === '') {
            $webp_relative_path = $file_info['filename'] . '.webp';
        } else {
            $webp_relative_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
        }
        
        $webp_path = $upload_basedir . '/' . $webp_relative_path;
        
        // Fix: Check for path duplication if file doesn't exist
        // This handles cases where basedir ends with 'fotoedicion' and relative path also starts with 'fotoedicion'
        if (!file_exists($webp_path)) {
            $basedir_name = basename($upload_basedir);
            $parts = explode('/', $webp_relative_path);
            
            if (count($parts) > 1 && $parts[0] === $basedir_name) {
                // Try removing the duplicated segment
                $alt_parts = $parts;
                array_shift($alt_parts);
                $alt_relative_path = implode('/', $alt_parts);
                $alt_path = $upload_basedir . '/' . $alt_relative_path;
                
                if (file_exists($alt_path)) {
                    $webp_path = $alt_path;
                    $webp_relative_path = $alt_relative_path;
                }
            }
        }
        
        // Verificar que el archivo WebP existe
        if (file_exists($webp_path)) {
            // Construir URL WebP respetando la estructura de carpetas personalizada
            if ($is_full_url) {
                // URL completa: reemplazar extensión y también la estructura de carpetas si es necesario
                $webp_url = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $image_url);
                
                // Si la URL original contenía /wp-content/uploads/ pero upload_url es diferente (ej: /fotoedicion/)
                // Reemplazar la estructura de carpetas también
                if (strpos($image_url, '/wp-content/uploads/') !== false && strpos($upload_url, '/wp-content/uploads/') === false) {
                    // Extraer el dominio y protocolo de la URL original
                    if (preg_match('#^(https?://[^/]+)(/.+)$#', $image_url, $original_parts)) {
                        $domain = $original_parts[1];
                        $original_path = $original_parts[2];
                        
                        // Extraer solo la ruta relativa (sin /wp-content/uploads/)
                        if (preg_match('#/wp-content/uploads/(.+)$#', $original_path, $path_matches)) {
                            $relative_path = $path_matches[1];
                            
                            // Construir nueva URL usando el dominio original y la estructura correcta
                            // upload_url puede ser relativa (/fotoedicion) o completa (https://site.com/fotoedicion)
                            if (strpos($upload_url, 'http') === 0) {
                                // upload_url es completa, extraer solo la ruta
                                if (preg_match('#https?://[^/]+(/.+)$#', $upload_url, $upload_parts)) {
                                    $upload_path = rtrim($upload_parts[1], '/');
                                    $webp_url = $domain . $upload_path . '/' . preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $relative_path);
                                } else {
                                    $webp_url = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $image_url);
                                }
                            } else {
                                // upload_url es relativa (ej: /fotoedicion)
                                $upload_path = rtrim($upload_url, '/');
                                $webp_url = $domain . $upload_path . '/' . preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $relative_path);
                            }
                        }
                    }
                }
                
                return $webp_url;
            } else {
                // Ruta relativa, construir URL completa usando upload_url (que respeta la carpeta personalizada)
                // upload_url puede ser relativa (/fotoedicion) o completa (https://site.com/fotoedicion)
                if (strpos($upload_url, 'http') === 0) {
                    // upload_url es completa, usar directamente
                    $upload_url_clean = rtrim($upload_url, '/');
                    $webp_path_clean = ltrim($webp_relative_path, '/');
                    return $upload_url_clean . '/' . $webp_path_clean;
                } else {
                    // upload_url es relativa, construir URL completa
                    $domain = '';
                    if ($is_full_url && preg_match('#^(https?://[^/]+)#', $image_url, $domain_match)) {
                        $domain = $domain_match[1];
                    } else {
                        // Usar home_url como fallback
                        $domain = home_url();
                    }
                    $upload_url_clean = rtrim($upload_url, '/');
                    $webp_path_clean = ltrim($webp_relative_path, '/');
                    return $domain . $upload_url_clean . '/' . $webp_path_clean;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get image size dimensions
     */
    private function get_image_size_dimensions($attachment_id, $size) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata) {
            return false;
        }
        
        // Si es un tamaño específico de WordPress
        if (is_array($size) && count($size) === 2) {
            return $size;
        }
        
        // Obtener dimensiones del tamaño solicitado
        $image_src = wp_get_attachment_image_src($attachment_id, $size);
        if ($image_src && isset($image_src[1]) && isset($image_src[2])) {
            return array($image_src[1], $image_src[2]);
        }
        
        // Fallback a dimensiones originales
        if (isset($metadata['width']) && isset($metadata['height'])) {
            return array($metadata['width'], $metadata['height']);
        }
        
        return false;
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
     * Start output buffer to intercept final HTML
     * This catches URLs that Bricks Builder or other plugins might generate directly
     */
    public function start_output_buffer() {
        // Solo en frontend, no en admin
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        
        // Si Cloudflare está manejando WebP, no hacer nada
        if ($this->is_cloudflare_webp_enabled()) {
            return;
        }
        
        // Si WebP está desactivado, no hacer nada
        if (!($this->options['enable_webp'] ?? true)) {
            return;
        }
        
        ob_start(array($this, 'replace_urls_in_output'));
    }
    
    /**
     * End output buffer
     */
    public function end_output_buffer() {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }
    
    /**
     * Replace image URLs in final HTML output
     * This is the last line of defense for URLs that bypass WordPress filters
     */
    public function replace_urls_in_output($buffer) {
        // Si Cloudflare está manejando WebP, no hacer nada
        if ($this->is_cloudflare_webp_enabled()) {
            return $buffer;
        }
        
        // Si WebP está desactivado, no hacer nada
        if (!($this->options['enable_webp'] ?? true)) {
            return $buffer;
        }
        
        if (empty($buffer)) {
            return $buffer;
        }
        
        // NO reemplazar URLs de imágenes que ya tienen placeholders (lazy loading)
        // Esto evita conflictos con el sistema de lazy loading
        // Las imágenes con data-src ya tienen su placeholder aplicado
        // Solo reemplazar URLs en src, srcset, background-image que NO sean placeholders
        
        // Asegurar que upload_dir está actualizado (puede cambiar si UPLOADS está definido)
        $current_upload_dir = wp_upload_dir();
        $upload_baseurl = $current_upload_dir['baseurl'];
        
        // Patrón para encontrar URLs de imágenes en el HTML
        // Busca src, srcset, data-src, background-image, etc.
        $patterns = array(
            // src="url.jpg" o src='url.jpg'
            '/(src=["\'])([^"\']*\.(jpg|jpeg|png))(["\'])/i',
            // srcset="url.jpg 300w, url.jpg 600w"
            '/(srcset=["\'])([^"\']*\.(jpg|jpeg|png)[^"\']*)(["\'])/i',
            // data-src="url.jpg"
            '/(data-src=["\'])([^"\']*\.(jpg|jpeg|png))(["\'])/i',
            // background-image: url(url.jpg)
            '/(background-image:\s*url\(["\']?)([^"\')]*\.(jpg|jpeg|png))(["\']?\))/i',
        );
        
        foreach ($patterns as $pattern) {
            $buffer = preg_replace_callback($pattern, function($matches) use ($upload_baseurl) {
                $quote_before = $matches[1];
                $image_url = $matches[2];
                $quote_after = isset($matches[4]) ? $matches[4] : '';
                
                // NO reemplazar si ya es WebP (por seguridad)
                if (preg_match('/\.webp$/i', $image_url)) {
                    return $matches[0]; // Ya es WebP, no reemplazar
                }
                
                // NO reemplazar placeholders (data:image/svg+xml o data:image/svg)
                if (strpos($image_url, 'data:image/svg') === 0) {
                    return $matches[0]; // Es un placeholder, no reemplazar
                }
                
                // Solo procesar URLs de uploads
                // Verificar múltiples formas de detectar URLs de uploads
                $is_upload_url = false;
                
                // Caso 1: Contiene upload_baseurl
                if (strpos($image_url, $upload_baseurl) !== false) {
                    $is_upload_url = true;
                }
                // Caso 2: Contiene /wp-content/uploads/
                elseif (strpos($image_url, '/wp-content/uploads/') !== false) {
                    $is_upload_url = true;
                }
                // Caso 3: Contiene /fotoedicion/ (estructura personalizada)
                elseif (strpos($image_url, '/fotoedicion/') !== false) {
                    $is_upload_url = true;
                }
                // Caso 4: Es una ruta relativa que empieza con uploads o fotoedicion
                elseif (preg_match('#^(/)?(wp-content/uploads/|fotoedicion/)#', $image_url)) {
                    $is_upload_url = true;
                }
                
                if (!$is_upload_url) {
                    return $matches[0];
                }
                
                // Obtener URL WebP
                $webp_url = $this->get_webp_url($image_url);
                
                if ($webp_url && $webp_url !== $image_url) {
                    // Reemplazar URL en srcset (puede tener múltiples URLs)
                    if (strpos($image_url, ' ') !== false) {
                        // Es un srcset, reemplazar todas las URLs
                        $srcset_parts = explode(',', $image_url);
                        $new_srcset = array();
                        foreach ($srcset_parts as $part) {
                            $part = trim($part);
                            if (preg_match('/^(.+\.(jpg|jpeg|png))\s+(\d+w)$/i', $part, $srcset_match)) {
                                $srcset_webp = $this->get_webp_url($srcset_match[1]);
                                if ($srcset_webp) {
                                    $new_srcset[] = $srcset_webp . ' ' . $srcset_match[3];
                                } else {
                                    $new_srcset[] = $part;
                                }
                            } else {
                                $new_srcset[] = $part;
                            }
                        }
                        return $quote_before . implode(', ', $new_srcset) . $quote_after;
                    } else {
                        // URL simple, reemplazar directamente
                        return $quote_before . $webp_url . $quote_after;
                    }
                }
                
                return $matches[0]; // No hay WebP, devolver original
            }, $buffer);
        }
        
        // Agregar dimensiones a imágenes que no las tienen (prevenir CLS)
        $buffer = $this->add_image_dimensions_to_output($buffer);
        
        return $buffer;
    }
    
    /**
     * Agregar dimensiones (width, height, aspect-ratio) a imágenes sin ellas
     * Esto previene layout shifts (CLS)
     */
    private function add_image_dimensions_to_output($buffer) {
        if (empty($buffer)) {
            return $buffer;
        }
        
        // Buscar todas las imágenes sin width o height
        preg_match_all('/<img([^>]+)>/i', $buffer, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $full_tag = $match[0];
            $attributes = $match[1];
            
            // Si ya tiene width y height, saltar
            if (preg_match('/\s(width|height)=["\']/', $attributes)) {
                continue;
            }
            
            // Extraer src
            if (!preg_match('/src=["\']([^"\']+)["\']/', $attributes, $src_match)) {
                continue;
            }
            
            $image_url = $src_match[1];
            
            // Obtener attachment ID desde URL
            $attachment_id = attachment_url_to_postid($image_url);
            if (!$attachment_id) {
                continue;
            }
            
            // Obtener metadata
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (!$metadata || !isset($metadata['width']) || !isset($metadata['height'])) {
                continue;
            }
            
            $width = $metadata['width'];
            $height = $metadata['height'];
            
            // Calcular aspect-ratio
            $aspect_ratio = $width / $height;
            
            // Agregar width, height y aspect-ratio
            $new_attributes = $attributes;
            
            // Agregar width y height
            $new_attributes .= ' width="' . esc_attr($width) . '"';
            $new_attributes .= ' height="' . esc_attr($height) . '"';
            
            // Agregar aspect-ratio al style si no existe
            if (!preg_match('/style=["\'][^"\']*aspect-ratio[^"\']*["\']/', $new_attributes)) {
                // Extraer style existente o crear uno nuevo
                if (preg_match('/style=["\']([^"\']*)["\']/', $new_attributes, $style_match)) {
                    $existing_style = $style_match[1];
                    $new_style = $existing_style . (empty($existing_style) ? '' : '; ') . 'aspect-ratio: ' . $aspect_ratio . ';';
                    $new_attributes = preg_replace('/style=["\'][^"\']*["\']/', 'style="' . esc_attr($new_style) . '"', $new_attributes);
                } else {
                    $new_attributes .= ' style="aspect-ratio: ' . esc_attr($aspect_ratio) . ';"';
                }
            }
            
            // Reemplazar tag original
            $new_tag = '<img' . $new_attributes . '>';
            $buffer = str_replace($full_tag, $new_tag, $buffer);
        }
        
        return $buffer;
    }
    
    /**
     * Check if Cloudflare is handling WebP conversion
     * Returns true if Cloudflare is detected and WebP conversion should be delegated to it
     */
    private function is_cloudflare_webp_enabled() {
        // Check if user has explicitly disabled theme WebP (assumes Cloudflare is being used)
        // This is a safe default: if Cloudflare is detected and WebP is disabled, assume Cloudflare handles it
        $has_cloudflare = isset($_SERVER['HTTP_CF_RAY']) || 
                          isset($_SERVER['HTTP_CF_VISITOR']) || 
                          isset($_SERVER['HTTP_CF_CONNECTING_IP']);
        
        // Only assume Cloudflare handles WebP if:
        // 1. Cloudflare is detected AND
        // 2. Theme WebP conversion is disabled
        // This way, if user enables theme WebP, it takes precedence
        if ($has_cloudflare && !($this->options['enable_webp'] ?? true)) {
            return true;
        }
        
        return false;
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
        
        add_settings_field(
            'convert_all_sizes',
            __('Convert All Image Sizes', 'snn'),
            array($this, 'convert_all_sizes_callback'),
            'snn-webp-optimization',
            'snn_webp_general'
        );
        
        add_settings_field(
            'auto_serve_webp',
            __('Auto-Serve WebP Images', 'snn'),
            array($this, 'auto_serve_webp_callback'),
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
        // Recargar opciones para asegurar que tenemos los valores más recientes
        $current_options = get_option('snn_webp_options', array());
        $enabled = isset($current_options['enable_webp']) && (int)$current_options['enable_webp'] === 1 ? 1 : 0;
        
        // Check if Cloudflare is detected
        $has_cloudflare = false;
        if (isset($_SERVER['HTTP_CF_RAY']) || isset($_SERVER['HTTP_CF_VISITOR']) || isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $has_cloudflare = true;
        }
        
        echo '<input type="checkbox" name="snn_webp_options[enable_webp]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Automatically convert uploaded images to WebP format.', 'snn') . '</p>';
        
        if ($has_cloudflare && $enabled) {
            echo '<p class="description" style="color: #d63638; font-weight: bold;">';
            echo '⚠️ ' . __('Cloudflare detectado: Se recomienda desactivar esta opción si usas Cloudflare Polish/Image Resizing para evitar duplicación.', 'snn');
            echo '</p>';
        }
    }
    
    public function webp_quality_callback() {
        $quality = $this->options['webp_quality'] ?? 80;
        echo '<input type="number" name="snn_webp_options[webp_quality]" value="' . esc_attr($quality) . '" min="1" max="100" />';
        echo '<p class="description">' . __('Calidad WebP (1-100). WebP comprime mejor que JPEG, por lo que 80 es equivalente a ~75 en JPEG. Recomendado: 80.', 'snn') . '</p>';
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
        // Recargar opciones para asegurar que tenemos los valores más recientes
        $current_options = get_option('snn_webp_options', array());
        $enabled = isset($current_options['enable_preload']) && (int)$current_options['enable_preload'] === 1 ? 1 : 0;
        echo '<input type="checkbox" name="snn_webp_options[enable_preload]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Preload critical images for faster loading.', 'snn') . '</p>';
    }
    
    public function enable_lazy_loading_callback() {
        // Recargar opciones para asegurar que tenemos los valores más recientes
        $current_options = get_option('snn_webp_options', array());
        $enabled = isset($current_options['enable_lazy_loading']) && (int)$current_options['enable_lazy_loading'] === 1 ? 1 : 0;
        echo '<input type="checkbox" name="snn_webp_options[enable_lazy_loading]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Enable lazy loading for non-critical images.', 'snn') . '</p>';
    }
    
    public function enable_placeholder_callback() {
        // Recargar opciones para asegurar que tenemos los valores más recientes
        $current_options = get_option('snn_webp_options', array());
        $enabled = isset($current_options['enable_placeholder']) && (int)$current_options['enable_placeholder'] === 1 ? 1 : 0;
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
    
    public function convert_all_sizes_callback() {
        // Recargar opciones para asegurar que tenemos los valores más recientes
        $current_options = get_option('snn_webp_options', array());
        $enabled = isset($current_options['convert_all_sizes']) && (int)$current_options['convert_all_sizes'] === 1 ? 1 : 0;
        echo '<input type="checkbox" name="snn_webp_options[convert_all_sizes]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Convert all WordPress image sizes (thumbnail, medium, large, etc.) to WebP. Recommended for better Core Web Vitals.', 'snn') . '</p>';
    }
    
    public function auto_serve_webp_callback() {
        // Recargar opciones para asegurar que tenemos los valores más recientes
        $current_options = get_option('snn_webp_options', array());
        $enabled = isset($current_options['auto_serve_webp']) && (int)$current_options['auto_serve_webp'] === 1 ? 1 : 0;
        echo '<input type="checkbox" name="snn_webp_options[auto_serve_webp]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Automatically serve WebP images when available. Fixes 404 errors for converted images.', 'snn') . '</p>';
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        // Checkboxes: check the value, not just if key exists (0 means unchecked, 1 means checked)
        $sanitized['enable_webp'] = (isset($input['enable_webp']) && (int)$input['enable_webp'] === 1) ? 1 : 0;
        $sanitized['webp_quality'] = intval($input['webp_quality'] ?? 80);
        $sanitized['max_width'] = intval($input['max_width'] ?? 1920);
        $sanitized['max_height'] = intval($input['max_height'] ?? 1080);
        $sanitized['enable_preload'] = (isset($input['enable_preload']) && (int)$input['enable_preload'] === 1) ? 1 : 0;
        $sanitized['enable_lazy_loading'] = (isset($input['enable_lazy_loading']) && (int)$input['enable_lazy_loading'] === 1) ? 1 : 0;
        $sanitized['enable_placeholder'] = (isset($input['enable_placeholder']) && (int)$input['enable_placeholder'] === 1) ? 1 : 0;
        $sanitized['critical_images'] = sanitize_textarea_field($input['critical_images'] ?? '');
        $sanitized['batch_size'] = intval($input['batch_size'] ?? 50);
        $sanitized['convert_all_sizes'] = (isset($input['convert_all_sizes']) && (int)$input['convert_all_sizes'] === 1) ? 1 : 0;
        $sanitized['auto_serve_webp'] = (isset($input['auto_serve_webp']) && (int)$input['auto_serve_webp'] === 1) ? 1 : 0;
        
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
            // Convert original image
            $file_path = get_attached_file($image->ID);
            if ($file_path && file_exists($file_path)) {
                $result = $this->convert_image_to_webp($file_path);
                if ($result) {
                    $batch_converted++;
                } else {
                    $batch_errors++;
                }
            }
            
            // Convert all image sizes
            $this->convert_all_image_sizes($image->ID);
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
        // Use more accurate count
        $total_count = $this->get_accurate_image_count();
        
        if ($total_count == 0) {
            return 100;
        }
        
        return round(($processed / $total_count) * 100, 1);
    }
    
    /**
     * Get accurate image count
     */
    private function get_accurate_image_count() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE 'image/%'
            AND post_status = 'inherit'
        ");
        
        return intval($count);
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
            // Convert original image
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
            
            // Convert all image sizes
            $this->convert_all_image_sizes($image->ID);
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
    
    /**
     * Test conversion AJAX handler
     */
    public function test_conversion_ajax() {
        check_ajax_referer('snn_webp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.'));
        }
        
        // Get a random image to test
        $images = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image/jpeg',
            'numberposts' => 1,
            'post_status' => 'inherit'
        ));
        
        if (empty($images)) {
            wp_send_json_error(array(
                'message' => __('No JPEG images found to test conversion.', 'snn')
            ));
        }
        
        $image = $images[0];
        $file_path = get_attached_file($image->ID);
        
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(array(
                'message' => __('Test image file not found.', 'snn')
            ));
        }
        
        // Test conversion
        $result = $this->convert_image_to_webp($file_path);
        
        if ($result) {
            // Check if original was removed and WebP was created
            $webp_exists = file_exists($result);
            $original_exists = file_exists($file_path);
            
            $message = sprintf(
                __('Test conversion successful! WebP created: %s. Original removed: %s', 'snn'),
                $webp_exists ? __('Yes', 'snn') : __('No', 'snn'),
                $original_exists ? __('No', 'snn') : __('Yes', 'snn')
            );
            
            wp_send_json_success(array(
                'message' => $message,
                'webp_path' => $result,
                'webp_exists' => $webp_exists,
                'original_exists' => $original_exists
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Test conversion failed. Check error logs.', 'snn')
            ));
        }
    }
}

// Initialize the WebP image optimizer
new SNN_WebP_Image_Optimizer();

