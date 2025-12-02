<?php
/**
 * Image Serving Verification
 * 
 * Verifies if images are being served directly by the server
 * without passing through WordPress, which improves performance
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Image Serving Verification Class
 */
class SNN_Image_Serving_Verification {
    
    /**
     * Check if an image request is being served directly
     * 
     * @param string $url The image URL to check
     * @return array Verification results
     */
    public static function verify_image_serving($url) {
        $results = array(
            'url' => $url,
            'served_directly' => false,
            'passes_through_wp' => false,
            'response_headers' => array(),
            'response_code' => 0,
            'file_exists' => false,
            'recommendations' => array()
        );
        
        // Parse URL
        $parsed_url = parse_url($url);
        if (!$parsed_url) {
            $results['recommendations'][] = 'URL inválida';
            return $results;
        }
        
        // Get file path
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $file_path = ABSPATH . ltrim($path, '/');
        
        // Check if file exists
        $results['file_exists'] = file_exists($file_path);
        
        if (!$results['file_exists']) {
            $results['recommendations'][] = 'El archivo no existe en el servidor';
            return $results;
        }
        
        // Make HTTP request to check headers
        $response = wp_remote_head($url, array(
            'timeout' => 5,
            'redirection' => 0
        ));
        
        if (is_wp_error($response)) {
            $results['recommendations'][] = 'Error al verificar: ' . $response->get_error_message();
            return $results;
        }
        
        $results['response_code'] = wp_remote_retrieve_response_code($response);
        $results['response_headers'] = wp_remote_retrieve_headers($response)->getAll();
        
        // Check if it passes through WordPress
        // WordPress typically adds headers like X-Powered-By, X-WP-*, etc.
        $wp_indicators = array(
            'x-powered-by',
            'x-wp-',
            'link', // WordPress adds link headers
        );
        
        foreach ($results['response_headers'] as $header_name => $header_value) {
            $header_lower = strtolower($header_name);
            foreach ($wp_indicators as $indicator) {
                if (strpos($header_lower, $indicator) !== false) {
                    $results['passes_through_wp'] = true;
                    break 2;
                }
            }
        }
        
        // Check Content-Type
        $content_type = isset($results['response_headers']['content-type']) 
            ? $results['response_headers']['content-type'] 
            : '';
        
        // If it's an image and doesn't pass through WP, it's served directly
        if (strpos($content_type, 'image/') === 0 && !$results['passes_through_wp']) {
            $results['served_directly'] = true;
        }
        
        // Generate recommendations
        if ($results['passes_through_wp']) {
            $results['recommendations'][] = 'La imagen está pasando por WordPress. Configura .htaccess para servirla directamente.';
        } else {
            $results['recommendations'][] = '✅ La imagen se está sirviendo directamente (óptimo)';
        }
        
        return $results;
    }
    
    /**
     * Verify favicon serving
     * Checks both WordPress Site Icon and favicon.ico
     * 
     * @return array Verification results
     */
    public static function verify_favicon_serving() {
        $results = array();
        
        // 1. Check WordPress Site Icon (configured in Customizer)
        $site_icon_id = get_option('site_icon');
        if ($site_icon_id) {
            $site_icon_url = wp_get_attachment_image_url($site_icon_id, 'full');
            if ($site_icon_url) {
                $result = self::verify_image_serving($site_icon_url);
                $result['type'] = 'wordpress_site_icon';
                $result['source'] = 'WordPress Customizer';
                $results[] = $result;
            }
        }
        
        // 2. Check favicon.ico in root
        $site_url = home_url('/');
        $favicon_url = $site_url . 'favicon.ico';
        $result = self::verify_image_serving($favicon_url);
        $result['type'] = 'favicon_ico';
        $result['source'] = 'Root favicon.ico';
        $results[] = $result;
        
        // 3. Check theme favicon
        $theme_favicon_url = get_stylesheet_directory_uri() . '/favicon.ico';
        $result = self::verify_image_serving($theme_favicon_url);
        $result['type'] = 'theme_favicon';
        $result['source'] = 'Theme favicon.ico';
        $results[] = $result;
        
        return $results;
    }
    
    /**
     * Get all image URLs from current page
     * Includes images from Bricks Builder, featured images, and content
     * 
     * @return array Array of image URLs
     */
    public static function get_page_images() {
        global $post;
        
        $images = array();
        $found_urls = array(); // Track URLs to avoid duplicates
        
        // 1. Get featured image
        if (is_singular() && $post && has_post_thumbnail($post->ID)) {
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');
            if ($thumbnail_url && !in_array($thumbnail_url, $found_urls)) {
                $images[] = array(
                    'url' => $thumbnail_url,
                    'type' => 'featured_image',
                    'id' => $thumbnail_id
                );
                $found_urls[] = $thumbnail_url;
            }
        }
        
        // 2. Get images from post content (for classic editor)
        if (is_singular() && $post) {
            $content = $post->post_content;
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $img_url) {
                    $img_url = esc_url_raw($img_url);
                    if (!in_array($img_url, $found_urls)) {
                        $images[] = array(
                            'url' => $img_url,
                            'type' => 'content_image'
                        );
                        $found_urls[] = $img_url;
                    }
                }
            }
        }
        
        // 3. Get images from Bricks Builder data
        if (function_exists('bricks_is_builder') && is_singular() && $post) {
            $bricks_data = get_post_meta($post->ID, '_bricks_page_content', true);
            
            if (!empty($bricks_data) && is_array($bricks_data)) {
                $bricks_images = self::extract_images_from_bricks_data($bricks_data);
                foreach ($bricks_images as $img_data) {
                    if (!in_array($img_data['url'], $found_urls)) {
                        $images[] = $img_data;
                        $found_urls[] = $img_data['url'];
                    }
                }
            }
        }
        
        // 4. Get images from rendered HTML (capture actual output)
        // This catches images from templates, headers, footers, etc.
        $rendered_images = self::get_images_from_rendered_html();
        foreach ($rendered_images as $img_data) {
            if (!in_array($img_data['url'], $found_urls)) {
                $images[] = $img_data;
                $found_urls[] = $img_data['url'];
            }
        }
        
        return $images;
    }
    
    /**
     * Extract images from Bricks Builder data structure
     * 
     * @param array $bricks_data Bricks elements data
     * @return array Array of image data
     */
    private static function extract_images_from_bricks_data($bricks_data) {
        $images = array();
        
        if (!is_array($bricks_data)) {
            return $images;
        }
        
        foreach ($bricks_data as $element) {
            if (!is_array($element)) {
                continue;
            }
            
            // Check for image element
            if (isset($element['name']) && $element['name'] === 'image' && isset($element['settings']['image']['url'])) {
                $img_url = $element['settings']['image']['url'];
                $images[] = array(
                    'url' => esc_url_raw($img_url),
                    'type' => 'bricks_image_element',
                    'element_id' => isset($element['id']) ? $element['id'] : ''
                );
            }
            
            // Check for background images
            if (isset($element['settings']['_background']['image']['url'])) {
                $bg_img_url = $element['settings']['_background']['image']['url'];
                $images[] = array(
                    'url' => esc_url_raw($bg_img_url),
                    'type' => 'bricks_background_image',
                    'element_id' => isset($element['id']) ? $element['id'] : ''
                );
            }
            
            // Recursively check children
            if (isset($element['children']) && is_array($element['children'])) {
                $child_images = self::extract_images_from_bricks_data($element['children']);
                $images = array_merge($images, $child_images);
            }
        }
        
        return $images;
    }
    
    /**
     * Get images from rendered HTML by making a request to the current page
     * This captures all images including those from templates, headers, footers
     * 
     * @return array Array of image data
     */
    private static function get_images_from_rendered_html() {
        $images = array();
        
        // Only run on frontend
        if (is_admin()) {
            return $images;
        }
        
        // Get current page URL
        $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        
        // Make a request to get the HTML (with timeout to avoid hanging)
        $response = wp_remote_get($current_url, array(
            'timeout' => 5,
            'sslverify' => false,
            'cookies' => $_COOKIE // Preserve cookies for logged-in users
        ));
        
        if (is_wp_error($response)) {
            return $images;
        }
        
        $html = wp_remote_retrieve_body($response);
        
        if (empty($html)) {
            return $images;
        }
        
        // Extract all image URLs from HTML
        // Match img src, background-image in style, and data-src (lazy loading)
        preg_match_all('/(?:src|data-src|background-image:\s*url\(["\']?)([^"\'\s\)]+\.(jpg|jpeg|png|gif|webp|svg|ico))/i', $html, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $img_url) {
                // Convert relative URLs to absolute
                if (strpos($img_url, 'http') !== 0) {
                    $img_url = home_url($img_url);
                }
                
                $img_url = esc_url_raw($img_url);
                
                // Skip data URIs and very small images
                if (strpos($img_url, 'data:') === 0 || strpos($img_url, 'base64') !== false) {
                    continue;
                }
                
                $images[] = array(
                    'url' => $img_url,
                    'type' => 'rendered_html_image'
                );
            }
        }
        
        return $images;
    }
    
    /**
     * Verify all images on current page
     * Limits to first 20 images to avoid performance issues
     * 
     * @param int $limit Maximum number of images to verify
     * @return array Verification results for all images
     */
    public static function verify_page_images($limit = 20) {
        $images = self::get_page_images();
        $results = array();
        
        // Limit number of images to verify (to avoid performance issues)
        $images = array_slice($images, 0, $limit);
        
        foreach ($images as $image) {
            $verification = self::verify_image_serving($image['url']);
            $verification['type'] = isset($image['type']) ? $image['type'] : 'unknown';
            if (isset($image['id'])) {
                $verification['attachment_id'] = $image['id'];
            }
            if (isset($image['element_id'])) {
                $verification['bricks_element_id'] = $image['element_id'];
            }
            $results[] = $verification;
        }
        
        return $results;
    }
    
    /**
     * Display verification results in admin
     */
    public static function display_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>Verificación de Servicio de Imágenes</h1>
            
            <h2>Favicon</h2>
            <?php
            $favicon_results = self::verify_favicon_serving();
            if (is_array($favicon_results) && isset($favicon_results[0])) {
                // Multiple favicon sources
                foreach ($favicon_results as $favicon_result) {
                    echo '<h3>Favicon: ' . esc_html($favicon_result['source']) . '</h3>';
                    self::display_result($favicon_result);
                }
            } else {
                // Single result (backward compatibility)
                self::display_result($favicon_results);
            }
            ?>
            
            <h2>Imágenes de la Página Actual</h2>
            <?php
            $page_images = self::verify_page_images(20);
            if (empty($page_images)) {
                echo '<p>No se encontraron imágenes en la página actual.</p>';
                echo '<p><em>Nota: Las imágenes de Bricks Builder se detectan mejor cuando se visita la página desde el frontend.</em></p>';
            } else {
                echo '<p><strong>Total de imágenes encontradas:</strong> ' . count($page_images) . ' (mostrando primeras 20)</p>';
                foreach ($page_images as $index => $result) {
                    echo '<h3>Imagen #' . ($index + 1) . ' - ' . esc_html($result['type']) . '</h3>';
                    self::display_result($result);
                }
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Display single verification result
     */
    private static function display_result($result) {
        ?>
        <div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #f9f9f9;">
            <h3><?php echo esc_html($result['url']); ?></h3>
            
            <p>
                <strong>Estado:</strong> 
                <?php if ($result['served_directly']): ?>
                    <span style="color: green;">✅ Servida directamente</span>
                <?php elseif ($result['passes_through_wp']): ?>
                    <span style="color: red;">❌ Pasa por WordPress</span>
                <?php else: ?>
                    <span style="color: orange;">⚠️ Estado desconocido</span>
                <?php endif; ?>
            </p>
            
            <p><strong>Archivo existe:</strong> <?php echo $result['file_exists'] ? 'Sí' : 'No'; ?></p>
            <p><strong>Código de respuesta:</strong> <?php echo esc_html($result['response_code']); ?></p>
            
            <?php if (!empty($result['response_headers'])): ?>
                <details>
                    <summary><strong>Headers de respuesta</strong></summary>
                    <pre style="background: #fff; padding: 10px; overflow-x: auto;"><?php 
                        print_r($result['response_headers']); 
                    ?></pre>
                </details>
            <?php endif; ?>
            
            <?php if (!empty($result['recommendations'])): ?>
                <p><strong>Recomendaciones:</strong></p>
                <ul>
                    <?php foreach ($result['recommendations'] as $rec): ?>
                        <li><?php echo esc_html($rec); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Add admin page if needed
if (is_admin()) {
    add_action('admin_menu', function() {
        add_submenu_page(
            'snn-settings',
            'Verificación de Imágenes',
            'Verificación de Imágenes',
            'manage_options',
            'snn-image-verification',
            array('SNN_Image_Serving_Verification', 'display_admin_page')
        );
    });
}

