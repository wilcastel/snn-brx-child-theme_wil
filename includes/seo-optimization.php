<?php
/**
 * SEO Optimization
 * 
 * Handles dynamic meta tags and basic SEO features.
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SNN_SEO_Optimization {
    
    public function __construct() {
        // Add Meta Box for SEO Settings
        add_action('add_meta_boxes', array($this, 'add_seo_meta_box'));
        
        // Save Meta Box Data
        add_action('save_post', array($this, 'save_seo_meta_box'));
        
        // Output Meta Tags in Head
        add_action('wp_head', array($this, 'output_meta_tags'), 1);
    }
    
    /**
     * Add SEO Meta Box
     */
    public function add_seo_meta_box() {
        $post_types = get_post_types(array('public' => true));
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'snn_seo_settings',
                __('SEO Settings', 'snn'),
                array($this, 'render_seo_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render SEO Meta Box
     */
    public function render_seo_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('snn_seo_save', 'snn_seo_nonce');
        
        // Get current value
        $meta_description = get_post_meta($post->ID, '_snn_meta_description', true);
        
        ?>
        <div class="snn-seo-field">
            <p>
                <label for="snn_meta_description"><strong><?php _e('Meta Description', 'snn'); ?></strong></label>
            </p>
            <textarea name="snn_meta_description" id="snn_meta_description" rows="4" style="width:100%;" placeholder="<?php _e('Enter a custom meta description. If left empty, the excerpt or content will be used.', 'snn'); ?>"><?php echo esc_textarea($meta_description); ?></textarea>
            <p class="description">
                <?php _e('Recommended length: 150-160 characters.', 'snn'); ?>
                <span id="snn-char-count" style="font-weight:bold; color:#666;">0</span> <?php _e('characters', 'snn'); ?>
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var $textarea = $('#snn_meta_description');
            var $count = $('#snn-char-count');
            
            function updateCount() {
                var len = $textarea.val().length;
                $count.text(len);
                if (len > 160) {
                    $count.css('color', '#d63638');
                } else {
                    $count.css('color', '#666');
                }
            }
            
            $textarea.on('input', updateCount);
            updateCount();
        });
        </script>
        <?php
    }
    
    /**
     * Save SEO Meta Box
     */
    public function save_seo_meta_box($post_id) {
        // Check nonce
        if (!isset($_POST['snn_seo_nonce']) || !wp_verify_nonce($_POST['snn_seo_nonce'], 'snn_seo_save')) {
            return;
        }
        
        // Check permissions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save fields
        if (isset($_POST['snn_meta_description'])) {
            update_post_meta($post_id, '_snn_meta_description', sanitize_textarea_field($_POST['snn_meta_description']));
        }
    }
    
    /**
     * Output Meta Tags
     */
    public function output_meta_tags() {
        // Allow on singular pages and the front page
        if (!is_singular() && !is_front_page()) {
            return;
        }
        
        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }
        
        // 1. Try Custom Field
        $description = get_post_meta($post_id, '_snn_meta_description', true);
        
        // 2. Default for Home Page (if custom field is empty)
        if (empty($description) && is_front_page()) {
            $description = "Noticias del Táchira, Venezuela y el mundo. Política, sucesos, deportes, opinión y más en La Nación Web.";
        }
        
        // 3. Try Excerpt
        if (empty($description) && has_excerpt($post_id)) {
            $description = get_the_excerpt($post_id);
        }
        
        // 4. Try Content (stripped and trimmed)
        if (empty($description)) {
            $post_obj = get_post($post_id);
            if ($post_obj) {
                $content = strip_shortcodes($post_obj->post_content);
                // Avoid running the_content filter to prevent infinite loops or heavy processing in head
                // $content = apply_filters('the_content', $content); 
                $content = str_replace(']]>', ']]&gt;', $content);
                $content = wp_strip_all_tags($content);
                
                // Remove line breaks and extra spaces
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);
                
                // Trim to ~160 chars
                if (mb_strlen($content) > 160) {
                    $description = mb_substr($content, 0, 157) . '...';
                } else {
                    $description = $content;
                }
            }
        }
        
        // 5. Final Fallback (Global)
        if (empty($description)) {
            $description = "Noticias del Táchira, Venezuela y el mundo. Política, sucesos, deportes, opinión y más en La Nación Web.";
        }
        
        // Final cleanup
        $description = trim($description);
        
        if (!empty($description)) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
            
            // Also add Open Graph description
            echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        }
    }
}

// Initialize
new SNN_SEO_Optimization();
