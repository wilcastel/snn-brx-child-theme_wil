<?php
/**
 * SNN Security & Optimization Settings
 * 
 * Unified security and optimization configuration for SNN-BRX-WIL theme.
 * Consolidates all security features and prepares structure for new optimizations.
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security & Optimization Settings Class
 */
class SNN_Security_Optimization {
    
    private $option_name = 'snn_security_optimization_options';
    private $option_group = 'snn_security_optimization_group';
    private $page_slug = 'snn-security-optimization';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        // Apply XML-RPC filter immediately if option is set (before plugins_loaded)
        $this->apply_xmlrpc_filter_early();
        // Use plugins_loaded with early priority for other security settings
        add_action('plugins_loaded', array($this, 'apply_security_settings'), 1);
        add_action('init', array($this, 'apply_security_settings'));
        add_action('init', array($this, 'register_og_image_size'));
        add_action('wp_enqueue_scripts', array($this, 'conditional_dashicons'));
        add_action('wp_footer', array($this, 'fix_bricks_social_sharing_urls'));
    }
    
    /**
     * Register custom image size for Open Graph (1200x630px)
     * This ensures we have properly sized images for social sharing
     */
    public function register_og_image_size() {
        // Register Open Graph image size (1200x630px - recommended for Facebook/WhatsApp)
        // Hard crop to ensure exact dimensions
        add_image_size('og-image', 1200, 630, true);
    }
    
    /**
     * Apply XML-RPC filter early (before plugins_loaded)
     * This is necessary because XML-RPC requests are processed very early
     */
    private function apply_xmlrpc_filter_early() {
        $options = get_option($this->option_name, array());
        if (isset($options['disable_xmlrpc']) && $options['disable_xmlrpc']) {
            add_filter('xmlrpc_enabled', '__return_false', 10, 1);
        }
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'snn-settings',
            __('Security & Optimization', 'snn'),
            __('Security & Optimization', 'snn'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Register settings and fields
     */
    public function register_settings() {
        // Register the main option
        register_setting(
            $this->option_group,
            $this->option_name,
            array($this, 'sanitize_options')
        );
        
        // Add sections
        $this->add_sections();
        
        // Add fields
        $this->add_fields();
    }
    
    /**
     * Add settings sections
     */
    private function add_sections() {
        $sections = array(
            'basic_security' => array(
                'title' => __('Basic Security Settings', 'snn'),
                'description' => __('Configure fundamental security features.', 'snn')
            ),
            'head_cleanup' => array(
                'title' => __('Advanced Head Cleanup', 'snn'),
                'description' => __('Remove unnecessary elements from HTML head. Note: WP Generator, RSD Link, WLW Manifest, Shortlink, REST API links, OEmbed links, and DNS Prefetch are already removed automatically.', 'snn')
            ),
            'loading_optimization' => array(
                'title' => __('Loading Optimization', 'snn'),
                'description' => __('Optimize CSS, JS, and font loading for better performance.', 'snn')
            ),
            'protocol_settings' => array(
                'title' => __('Protocol Settings', 'snn'),
                'description' => __('Configure HTTPS, CORS, and mixed content handling.', 'snn')
            ),
            'resource_management' => array(
                'title' => __('Resource Management', 'snn'),
                'description' => __('Manage WordPress resources and third-party integrations.', 'snn')
            )
        );
        
        foreach ($sections as $id => $section) {
            add_settings_section(
                $id,
                $section['title'],
                function() use ($section) {
                    echo '<p>' . esc_html($section['description']) . '</p>';
                },
                $this->page_slug
            );
        }
    }
    
    /**
     * Add settings fields
     */
    private function add_fields() {
        $fields = $this->get_all_fields();
        
        foreach ($fields as $field) {
            add_settings_field(
                $field['id'],
                $field['label'],
                array($this, 'render_field'),
                $this->page_slug,
                $field['section'],
                array(
                    'field_id' => $field['id'],
                    'field_type' => $field['type'],
                    'description' => $field['description'],
                    'options' => isset($field['options']) ? $field['options'] : array()
                )
            );
        }
    }
    
    /**
     * Get all field definitions
     */
    private function get_all_fields() {
        return array(
            // Basic Security Settings
            array(
                'id' => 'disable_xmlrpc',
                'label' => __('Disable XML-RPC', 'snn'),
                'type' => 'checkbox',
                'section' => 'basic_security',
                'description' => __('Disable XML-RPC functionality in WordPress.', 'snn')
            ),
            array(
                'id' => 'disable_json_api_guests',
                'label' => __('Disable JSON API for Guests', 'snn'),
                'type' => 'checkbox',
                'section' => 'basic_security',
                'description' => __('Disable JSON API (wp-json) for users who are not logged in.', 'snn')
            ),
            array(
                'id' => 'disable_file_editing',
                'label' => __('Disable File Editing', 'snn'),
                'type' => 'checkbox',
                'section' => 'basic_security',
                'description' => __('Disable file editing from the WordPress dashboard.', 'snn')
            ),
            array(
                'id' => 'remove_rss_feeds',
                'label' => __('Remove RSS Feeds', 'snn'),
                'type' => 'checkbox',
                'section' => 'basic_security',
                'description' => __('Remove RSS feed links from your website\'s HTML source code.', 'snn')
            ),
            array(
                'id' => 'hide_wp_version',
                'label' => __('Hide WP Version', 'snn'),
                'type' => 'checkbox',
                'section' => 'basic_security',
                'description' => __('Remove the WordPress version number from your website\'s HTML source code.', 'snn')
            ),
            array(
                'id' => 'disable_bundled_themes',
                'label' => __('Disable Bundled Themes', 'snn'),
                'type' => 'checkbox',
                'section' => 'basic_security',
                'description' => __('Disable bundled theme install when upgrading WordPress.', 'snn')
            ),
            array(
                'id' => 'enable_math_captcha',
                'label' => __('Enable Math Captcha', 'snn'),
                'type' => 'checkbox',
                'section' => 'basic_security',
                'description' => __('Add a math captcha challenge on the login page to improve security.', 'snn')
            ),
            array(
                'id' => 'disable_emojis',
                'label' => __('Disable Emojis', 'snn'),
                'type' => 'checkbox',
                'section' => 'basic_security',
                'description' => __('Disable emoji support in WordPress both frontend and wp-admin.', 'snn')
            ),
            array(
                'id' => 'disable_gravatar',
                'label' => __('Disable Gravatar', 'snn'),
                'type' => 'checkbox',
                'section' => 'basic_security',
                'description' => __('Disable Gravatar support throughout the site.', 'snn')
            ),
            
            // Advanced Head Cleanup
            // Note: WP Generator, RSD Link, WLW Manifest, Shortlink, REST API links, 
            // OEmbed links, and DNS Prefetch are already removed automatically by head-optimization.php
            array(
                'id' => 'remove_wp_block_library',
                'label' => __('Remove WP Block Library', 'snn'),
                'type' => 'checkbox',
                'section' => 'head_cleanup',
                'description' => __('Remove WordPress block library CSS.', 'snn')
            ),
            
            // Loading Optimization
            array(
                'id' => 'optimize_css_loading',
                'label' => __('Optimize CSS Loading', 'snn'),
                'type' => 'checkbox',
                'section' => 'loading_optimization',
                'description' => __('Optimize CSS loading with font preloading.', 'snn')
            ),
            array(
                'id' => 'keep_social_meta',
                'label' => __('Keep Social Meta Tags', 'snn'),
                'type' => 'checkbox',
                'section' => 'loading_optimization',
                'description' => __('Generate Open Graph and Twitter Card meta tags for social media sharing (WhatsApp, Facebook, Twitter, etc.).', 'snn')
            ),
            array(
                'id' => 'keep_seo_meta',
                'label' => __('Keep SEO Meta Tags', 'snn'),
                'type' => 'checkbox',
                'section' => 'loading_optimization',
                'description' => __('Generate canonical and robots meta tags for SEO.', 'snn')
            ),
            
            // Protocol Settings
            array(
                'id' => 'force_https',
                'label' => __('Force HTTPS', 'snn'),
                'type' => 'checkbox',
                'section' => 'protocol_settings',
                'description' => __('Force redirection to HTTPS (use with caution).', 'snn')
            ),
            array(
                'id' => 'fix_mixed_content',
                'label' => __('Fix Mixed Content', 'snn'),
                'type' => 'checkbox',
                'section' => 'protocol_settings',
                'description' => __('Automatically fix mixed content URLs.', 'snn')
            ),
            array(
                'id' => 'add_cors_headers',
                'label' => __('Add CORS Headers', 'snn'),
                'type' => 'checkbox',
                'section' => 'protocol_settings',
                'description' => __('Add CORS headers for same-domain resources.', 'snn')
            ),
            array(
                'id' => 'protocol_detection',
                'label' => __('Protocol Detection', 'snn'),
                'type' => 'checkbox',
                'section' => 'protocol_settings',
                'description' => __('Add protocol detection in JavaScript.', 'snn')
            ),
            array(
                'id' => 'fix_font_urls',
                'label' => __('Fix Font URLs', 'snn'),
                'type' => 'checkbox',
                'section' => 'protocol_settings',
                'description' => __('Fix font URLs to prevent CORS errors.', 'snn')
            ),
            
            // Resource Management
            array(
                'id' => 'conditional_dashicons',
                'label' => __('Conditional Dashicons', 'snn'),
                'type' => 'checkbox',
                'section' => 'resource_management',
                'description' => __('Load Dashicons only for logged-in users with editor+ permissions.', 'snn')
            )
        );
    }
    
    /**
     * Render field
     */
    public function render_field($args) {
        $options = get_option($this->option_name, array());
        $field_id = $args['field_id'];
        $field_type = $args['field_type'];
        $description = $args['description'];
        
        $value = isset($options[$field_id]) ? $options[$field_id] : 0;
        
        switch ($field_type) {
            case 'checkbox':
                echo '<input type="checkbox" name="' . $this->option_name . '[' . $field_id . ']" value="1" ' . checked($value, 1, false) . '>';
                break;
            case 'text':
                echo '<input type="text" name="' . $this->option_name . '[' . $field_id . ']" value="' . esc_attr($value) . '" class="regular-text">';
                break;
            case 'textarea':
                echo '<textarea name="' . $this->option_name . '[' . $field_id . ']" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
                break;
        }
        
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        // Get all field IDs to ensure checkboxes are handled correctly
        $all_fields = $this->get_all_fields();
        $field_ids = array();
        foreach ($all_fields as $field) {
            $field_ids[] = $field['id'];
        }
        
        $sanitized = array();
        
        // Get current options to preserve unchecked checkboxes
        $current_options = get_option($this->option_name, array());
        
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                if (in_array($key, $field_ids)) {
                    // For checkboxes, value is '1' when checked
                    if (is_numeric($value) || $value === '1' || $value === 1) {
                        $sanitized[$key] = 1;
                    } else {
                        $sanitized[$key] = sanitize_text_field($value);
                    }
                }
            }
        }
        
        // For checkboxes not in input (unchecked), set to 0
        foreach ($field_ids as $field_id) {
            $field = array_filter($all_fields, function($f) use ($field_id) {
                return $f['id'] === $field_id;
            });
            $field = reset($field);
            
            if ($field && $field['type'] === 'checkbox') {
                if (!isset($sanitized[$field_id])) {
                    $sanitized[$field_id] = 0;
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Security & Optimization Settings', 'snn'); ?></h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->page_slug);
                submit_button();
                ?>
            </form>
        </div>
        
        <style>
        [type="checkbox"] {
            width: 18px !important;
            height: 18px !important;
            float: left;
            margin-right: 10px !important;
        }
        .form-table th {
            width: 200px;
        }
        </style>
        <?php
    }
    
    /**
     * Apply security settings
     */
    public function apply_security_settings() {
        $options = get_option($this->option_name, array());
        
        // Apply optimizations regardless of settings (always active)
        $this->apply_head_optimizations();
        
        // Basic Security Settings
        if (isset($options['disable_xmlrpc']) && $options['disable_xmlrpc']) {
            add_filter('xmlrpc_enabled', '__return_false');
        }
        
        if (isset($options['disable_json_api_guests']) && $options['disable_json_api_guests']) {
            add_filter('rest_authentication_errors', array($this, 'disable_json_for_guests'), 99);
        }
        
        if (isset($options['disable_file_editing']) && $options['disable_file_editing']) {
            define('DISALLOW_FILE_EDIT', true);
        }
        
        if (isset($options['remove_rss_feeds']) && $options['remove_rss_feeds']) {
            $this->remove_rss_feeds();
        }
        
        if (isset($options['hide_wp_version']) && $options['hide_wp_version']) {
            add_filter('the_generator', '__return_empty_string');
        }
        
        if (isset($options['disable_bundled_themes']) && $options['disable_bundled_themes']) {
            define('CORE_UPGRADE_SKIP_NEW_BUNDLED', true);
        }
        
        if (isset($options['enable_math_captcha']) && $options['enable_math_captcha']) {
            $this->enable_math_captcha();
        }
        
        if (isset($options['disable_emojis']) && $options['disable_emojis']) {
            $this->disable_emojis();
        }
        
        if (isset($options['disable_gravatar']) && $options['disable_gravatar']) {
            add_filter('get_avatar', '__return_empty_string');
        }
        
        // Advanced Head Cleanup
        // Note: WP Generator, RSD Link, WLW Manifest, Shortlink, REST API links,
        // OEmbed links, and DNS Prefetch are already removed automatically by head-optimization.php
        // Emoji scripts are handled by disable_emojis() option
        if (isset($options['remove_wp_block_library']) && $options['remove_wp_block_library']) {
            // Priority 99999 to ensure it runs AFTER Jetpack and other plugins
            add_action('wp_enqueue_scripts', array($this, 'remove_wp_block_library'), 99999);
            // Also remove on wp_head as fallback
            add_action('wp_head', array($this, 'remove_wp_block_library_head'), 1);
        }
        
        // Loading Optimization
        if (isset($options['optimize_css_loading']) && $options['optimize_css_loading']) {
            // Use priority 5 to ensure styles are registered before we detect them
            add_action('wp_head', array($this, 'optimize_css_loading'), 5);
        }
        
        if (isset($options['keep_social_meta']) && $options['keep_social_meta']) {
            // Priority 1 to ensure social meta tags are added early, but after plugins like Yoast/Rank Math
            // Use template_redirect to ensure we're in the right context
            add_action('wp_head', array($this, 'add_social_meta_tags'), 1);
        }
        
        if (isset($options['keep_seo_meta']) && $options['keep_seo_meta']) {
            // Priority 1 to ensure SEO meta tags are added early
            add_action('wp_head', array($this, 'add_seo_meta_tags'), 1);
        }
        
        // Protocol Settings
        if (isset($options['force_https']) && $options['force_https']) {
            add_action('template_redirect', array($this, 'force_https'));
        }
        
        if (isset($options['fix_mixed_content']) && $options['fix_mixed_content']) {
            add_action('wp_head', array($this, 'fix_mixed_content'));
        }
        
        if (isset($options['add_cors_headers']) && $options['add_cors_headers']) {
            add_action('send_headers', array($this, 'add_cors_headers'));
        }
        
        if (isset($options['protocol_detection']) && $options['protocol_detection']) {
            add_action('wp_footer', array($this, 'protocol_detection_script'));
        }
        
        if (isset($options['fix_font_urls']) && $options['fix_font_urls']) {
            add_action('wp_head', array($this, 'fix_font_urls'));
        }
    }
    
    /**
     * Apply head optimizations (always active)
     */
    private function apply_head_optimizations() {
        // Remove duplicate CSS loading
        add_action('wp_enqueue_scripts', array($this, 'prevent_duplicate_css'), 20);
        
        // Conditional Dashicons loading
        add_action('wp_enqueue_scripts', array($this, 'conditional_dashicons'), 20);
        
        // Limit inline CSS
        add_filter('styles_inline_size_limit', function() {
            return 0; // Force external files instead of inline CSS
        });
        
        // Note: WP Generator, RSD Link, WLW Manifest, and DNS Prefetch are already
        // removed automatically by head-optimization.php, so we don't duplicate here
        
        // Remove admin bar for non-admin users
        if (!current_user_can('administrator')) {
            add_filter('show_admin_bar', '__return_false');
        }
    }
    
    /**
     * Prevent duplicate CSS loading
     */
    public function prevent_duplicate_css() {
        // Remove preload if stylesheet is already loaded
        add_action('wp_head', function() {
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                var preloadLinks = document.querySelectorAll("link[rel=\'preload\'][as=\'style\']");
                var stylesheetLinks = document.querySelectorAll("link[rel=\'stylesheet\']");
                
                preloadLinks.forEach(function(preload) {
                    var preloadHref = preload.href;
                    stylesheetLinks.forEach(function(stylesheet) {
                        if (stylesheet.href === preloadHref) {
                            preload.remove();
                        }
                    });
                });
            });
            </script>';
        }, 1);
    }
    
    /**
     * Conditional Dashicons loading
     */
    public function conditional_dashicons() {
        // Only load Dashicons for logged-in users with editor+ permissions
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_dequeue_style('dashicons');
        }
    }
    
    /**
     * Disable JSON API for guests
     */
    public function disable_json_for_guests($result) {
        // Always allow logged-in users
        if (is_user_logged_in()) {
            return $result;
        }
        
        // Get request URI to check for allowed endpoints
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Always allow our custom endpoints (like snn/v1 for like buttons, etc.)
        if (!empty($request_uri) && strpos($request_uri, '/wp-json/snn/') !== false) {
            return $result; // Allow custom endpoints
        }
        
        // Always allow WindPress endpoints (required for Tailwind CSS functionality)
        if (!empty($request_uri) && strpos($request_uri, '/wp-json/windpress/') !== false) {
            return $result; // Allow WindPress endpoints
        }
        
        // If there's already an error, return it
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Block all other endpoints for non-logged-in users
        // This includes when $result is null (public access allowed by default)
        return new WP_Error('rest_not_logged_in', __('You are not logged in.', 'snn'), array('status' => 401));
    }
    
    /**
     * Remove RSS feeds
     */
    private function remove_rss_feeds() {
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'wlwmanifest_link');
    }
    
    /**
     * Enable math captcha
     */
    private function enable_math_captcha() {
        // Include math captcha functionality
        require_once SNN_PATH . 'includes/login-math-captcha.php';
    }
    
    /**
     * Disable emojis
     */
    private function disable_emojis() {
        // Front-end removal
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        
        // Feeds
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        
        // Emails
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        
        // Embeds
        remove_action('embed_head', 'print_emoji_detection_script');
        remove_action('embed_print_styles', 'print_emoji_styles');
        
        // TinyMCE editor
        add_filter('tiny_mce_plugins', array($this, 'disable_emojis_tinymce'));
        
        // Admin area
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
    }
    
    /**
     * Disable emojis in TinyMCE
     */
    public function disable_emojis_tinymce($plugins) {
        if (is_array($plugins)) {
            return array_diff($plugins, array('wpemoji'));
        }
        return array();
    }
    
    /**
     * Remove WordPress block library
     * CRITICAL: This must run AFTER Jetpack which enqueues wp-block-library
     */
    public function remove_wp_block_library() {
        wp_dequeue_style('wp-block-library');
        wp_deregister_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_deregister_style('wp-block-library-theme');
    }
    
    /**
     * Remove WordPress block library from head (fallback)
     */
    public function remove_wp_block_library_head() {
        wp_dequeue_style('wp-block-library');
        wp_deregister_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_deregister_style('wp-block-library-theme');
    }
    
    /**
     * Optimize CSS loading
     */
    public function optimize_css_loading() {
        // Get all enqueued styles
        global $wp_styles;
        $critical_styles = array();
        
        // Always preload the main theme stylesheet
        $main_stylesheet = get_stylesheet_uri();
        if ($main_stylesheet) {
            $critical_styles[] = $main_stylesheet;
        }
        
        // Add critical stylesheets that should be preloaded
        // These are typically loaded early and are important for LCP
        $critical_handles = array(
            'bricks-frontend',        // Bricks Builder main CSS (critical for rendering)
            'snn-theme-specific',     // Theme-specific CSS
        );
        
        // Note: WindPress CSS is injected via scripts (windpress:metadata, windpress:vfs)
        // and cannot be preloaded as a traditional stylesheet. The CSS is compiled
        // dynamically by the WindPress observer script, so preloading the observer
        // script itself would be more beneficial. However, since it's already loaded
        // with defer, we focus on preloading traditional stylesheets only.
        
        if (isset($wp_styles) && is_object($wp_styles)) {
            foreach ($critical_handles as $handle) {
                if (isset($wp_styles->registered[$handle])) {
                    $style = $wp_styles->registered[$handle];
                    $src = $style->src;
                    
                    // Get the full URL using wp_style_loader_src filter logic
                    if (!preg_match('/^(https?:)?\/\//', $src)) {
                        // Relative URL - convert to absolute
                        if (strpos($src, '/') === 0) {
                            // Absolute path from root
                            $src = site_url($src);
                        } else {
                            // Relative path - prepend stylesheet directory
                            $src = get_stylesheet_directory_uri() . '/' . $src;
                        }
                    }
                    
                    // Add version query if exists
                    if (!empty($style->ver)) {
                        $src = add_query_arg('ver', $style->ver, $src);
                    }
                    
                    // Avoid duplicates
                    if (!in_array($src, $critical_styles)) {
                        $critical_styles[] = $src;
                    }
                }
            }
        }
        
        // Output preload links for critical stylesheets
        foreach ($critical_styles as $stylesheet_url) {
            echo '<link rel="preload" href="' . esc_url($stylesheet_url) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
        }
        
        // Preload WindPress observer script (processes Tailwind CSS)
        // WindPress injects CSS via scripts (windpress:metadata, windpress:vfs),
        // so preloading the observer script helps load Tailwind CSS faster
        // Note: WindPress scripts are injected directly, not via wp_enqueue_script,
        // so we need to check if the plugin is active and construct the path
        if (!function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        if (is_plugin_active('windpress/windpress.php') || 
            defined('WINDPRESS_VERSION') || 
            class_exists('WindPress')) {
            // Try to find WindPress observer script in the plugin directory
            $windpress_observer_pattern = WP_PLUGIN_DIR . '/windpress/build/assets/packages/core/tailwindcss/play/observer-*.js';
            $observer_files = glob($windpress_observer_pattern);
            
            if (!empty($observer_files)) {
                // Get the most recent observer file (in case there are multiple versions)
                $observer_file = end($observer_files);
                $observer_url = str_replace(WP_PLUGIN_DIR, plugins_url(), $observer_file);
                
                // Preload as script (it's JavaScript that processes the CSS)
                echo '<link rel="preload" href="' . esc_url($observer_url) . '" as="script">' . "\n";
            }
        }
        
        // Add noscript fallback for browsers without JavaScript
        if (!empty($critical_styles)) {
            echo '<noscript>' . "\n";
            foreach ($critical_styles as $stylesheet_url) {
                echo '<link rel="stylesheet" href="' . esc_url($stylesheet_url) . '">' . "\n";
            }
            echo '</noscript>' . "\n";
        }
    }
    
    /**
     * Preload critical fonts
     * DEPRECATED: Esta funcionalidad ha sido movida a assets-optimization.php para evitar duplicados
     * Mantenida solo para compatibilidad con código legacy
     */
    /**
     * Force HTTPS
     */
    public function force_https() {
        if (!is_ssl()) {
            wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301);
            exit();
        }
    }
    
    /**
     * Fix mixed content
     */
    public function fix_mixed_content() {
        echo '<meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">';
    }
    
    /**
     * Add CORS headers
     * Note: WordPress REST API already handles CORS headers, so we exclude API endpoints
     * to avoid conflicts with plugins like WindPress
     */
    public function add_cors_headers() {
        // Don't add CORS headers for REST API endpoints
        // WordPress REST API and plugins (like WindPress) handle their own CORS headers
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Exclude REST API endpoints (wp-json)
        if (!empty($request_uri) && strpos($request_uri, '/wp-json/') !== false) {
            return; // Let WordPress REST API handle CORS for API endpoints
        }
        
        // Only add CORS headers for non-API requests (same-domain resources)
        header('Access-Control-Allow-Origin: ' . home_url());
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
    
    /**
     * Protocol detection script
     */
    public function protocol_detection_script() {
        ?>
        <script>
        if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
            location.replace('https:' + window.location.href.substring(window.location.protocol.length));
        }
        </script>
        <?php
    }
    
    /**
     * Fix font URLs
     */
    public function fix_font_urls() {
        echo '<style>@font-face { font-display: swap; }</style>';
    }
    
    /**
     * Conditional Dashicons loading (updated method)
     */
    public function conditional_dashicons_new() {
        $options = get_option($this->option_name, array());
        
        if (isset($options['conditional_dashicons']) && $options['conditional_dashicons']) {
            if (is_user_logged_in() && current_user_can('edit_posts')) {
                wp_enqueue_style('dashicons');
            }
        } else {
            wp_enqueue_style('dashicons');
        }
    }
    
    /**
     * Add social meta tags (Open Graph and Twitter Cards)
     * Only adds if not already added by SEO plugins (Yoast, Rank Math, etc.)
     */
    public function add_social_meta_tags() {
        // Check if SEO plugins are active and already adding meta tags
        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            return; // Yoast handles this
        }
        
        // Rank Math
        if (defined('RANK_MATH_VERSION')) {
            return; // Rank Math handles this
        }
        
        // All in One SEO
        if (defined('AIOSEO_VERSION')) {
            return; // AIOSEO handles this
        }
        
        // Only add if we're on a singular post/page, front page, or home
        // Note: is_home() returns true for the blog posts page, is_front_page() for the front page
        if (!is_singular() && !is_front_page() && !is_home()) {
            return;
        }
        
        global $post;
        
        // Get post/page data
        if (is_singular() && $post) {
            $title = get_the_title($post->ID);
            $description = '';
            $image = '';
            $url = get_permalink($post->ID);
            
            // Get description (excerpt or first paragraph)
            if (has_excerpt($post->ID)) {
                $description = get_the_excerpt($post->ID);
            } else {
                $description = wp_trim_words(strip_shortcodes($post->post_content), 30, '...');
            }
            
            // Get featured image or first image from content
            if (has_post_thumbnail($post->ID)) {
                $image_id = get_post_thumbnail_id($post->ID);
                $image = $this->get_social_image_url($image_id);
            } else {
                // Try to get first image from content
                $content = $post->post_content;
                // Try to get attachment ID from image in content
                if (preg_match('/wp-image-(\d+)/', $content, $id_matches)) {
                    $image_id = intval($id_matches[1]);
                    $image = $this->get_social_image_url($image_id);
                } elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches)) {
                    $image = esc_url($matches[1]);
                    // Convert to absolute URL if relative
                    if (strpos($image, 'http') !== 0) {
                        $image = home_url($image);
                    }
                    // Ensure HTTPS
                    $image = set_url_scheme($image, 'https');
                }
            }
            
            // Fallback to default image or site logo
            if (empty($image)) {
                // Use predefined default image for posts
                // Note: favorit.jpg is already in JPG format, perfect for WhatsApp
                $default_image = 'https://lanacionweb.com/fotoedicion/2025/08/favorit.jpg';
                $image = set_url_scheme($default_image, 'https');
                
                // If default image doesn't work, fallback to site logo
                if (empty($image)) {
                    $custom_logo_id = get_theme_mod('custom_logo');
                    if ($custom_logo_id) {
                        $image = $this->get_social_image_url($custom_logo_id);
                    }
                }
            }
            
            // Ensure image is not WebP for WhatsApp compatibility
            // Convert to original format if it's WebP
            if (!empty($image) && strpos($image, '.webp') !== false) {
                if (has_post_thumbnail($post->ID)) {
                    $image_id = get_post_thumbnail_id($post->ID);
                    $original_image = $this->convert_webp_to_original($image, $image_id);
                    if ($original_image && $original_image !== $image) {
                        $image = $original_image;
                    }
                } elseif (preg_match('/wp-image-(\d+)/', $post->post_content ?? '', $id_matches)) {
                    $image_id = intval($id_matches[1]);
                    $original_image = $this->convert_webp_to_original($image, $image_id);
                    if ($original_image && $original_image !== $image) {
                        $image = $original_image;
                    }
                } else {
                    // For URLs without attachment ID, try to replace .webp with .jpg
                    $image = str_replace('.webp', '.jpg', $image);
                }
            }
            
            // Ensure image is absolute URL with HTTPS
            if (!empty($image) && strpos($image, 'http') !== 0) {
                $image = home_url($image);
            }
            if (!empty($image)) {
                $image = set_url_scheme($image, 'https');
            }
            
            // Site name
            $site_name = get_bloginfo('name');
            
            // Output Open Graph meta tags
            echo "\n<!-- SNN Social Meta Tags -->\n";
            echo '<meta property="og:type" content="article" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
            if (!empty($description)) {
                echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
            }
            if (!empty($image)) {
                // WhatsApp requires HTTPS and specific image formats
                // Use original format (JPG/PNG) instead of WebP for better compatibility
                $og_image_url = $image;
                
                // If image is WebP, try to get original format for WhatsApp
                if (strpos($image, '.webp') !== false && has_post_thumbnail($post->ID)) {
                    $image_id = get_post_thumbnail_id($post->ID);
                    $original_url = $this->convert_webp_to_original($image, $image_id);
                    if ($original_url && $original_url !== $image) {
                        $og_image_url = $original_url;
                    }
                }
                
                echo '<meta property="og:image" content="' . esc_url($og_image_url) . '" />' . "\n";
                
                // WhatsApp requires og:image:secure_url (HTTPS)
                echo '<meta property="og:image:secure_url" content="' . esc_url($og_image_url) . '" />' . "\n";
                
                // Get actual image dimensions if we have attachment ID
                $image_dimensions = false;
                if (has_post_thumbnail($post->ID)) {
                    $image_id = get_post_thumbnail_id($post->ID);
                    $image_dimensions = $this->get_social_image_dimensions($image_id, $og_image_url);
                } elseif (preg_match('/wp-image-(\d+)/', $post->post_content ?? '', $id_matches)) {
                    // Try to get dimensions from image in content
                    $image_id = intval($id_matches[1]);
                    $image_dimensions = $this->get_social_image_dimensions($image_id, $og_image_url);
                }
                
                // Use actual dimensions if available, otherwise use recommended defaults
                if ($image_dimensions) {
                    echo '<meta property="og:image:width" content="' . esc_attr($image_dimensions['width']) . '" />' . "\n";
                    echo '<meta property="og:image:height" content="' . esc_attr($image_dimensions['height']) . '" />' . "\n";
                } else {
                    // Fallback: use recommended dimensions (Facebook/WhatsApp will verify actual size)
                    // This applies to default image (favorit.jpg) and images without metadata
                    echo '<meta property="og:image:width" content="1200" />' . "\n";
                    echo '<meta property="og:image:height" content="630" />' . "\n";
                }
                
                // Add og:image:type (WhatsApp prefers JPG/PNG over WebP)
                $image_extension = strtolower(pathinfo(parse_url($og_image_url, PHP_URL_PATH), PATHINFO_EXTENSION));
                if ($image_extension === 'jpg' || $image_extension === 'jpeg') {
                    echo '<meta property="og:image:type" content="image/jpeg" />' . "\n";
                } elseif ($image_extension === 'png') {
                    echo '<meta property="og:image:type" content="image/png" />' . "\n";
                } elseif ($image_extension === 'webp') {
                    // Only if we couldn't convert to original format
                    echo '<meta property="og:image:type" content="image/webp" />' . "\n";
                }
            }
            echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";
            echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '" />' . "\n";
            
            // Output Twitter Card meta tags
            // Twitter requires specific format and additional meta tags
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
            if (!empty($description)) {
                // Twitter description should be max 200 characters
                $twitter_description = mb_substr($description, 0, 200);
                echo '<meta name="twitter:description" content="' . esc_attr($twitter_description) . '" />' . "\n";
            }
            if (!empty($image)) {
                // Use same image URL as Open Graph (with WebP conversion if needed)
                $twitter_image_url = $image;
                if (strpos($image, '.webp') !== false && has_post_thumbnail($post->ID)) {
                    $image_id = get_post_thumbnail_id($post->ID);
                    $original_url = $this->convert_webp_to_original($image, $image_id);
                    if ($original_url && $original_url !== $image) {
                        $twitter_image_url = $original_url;
                    }
                }
                echo '<meta name="twitter:image" content="' . esc_url($twitter_image_url) . '" />' . "\n";
                // Twitter also supports alt text
                echo '<meta name="twitter:image:alt" content="' . esc_attr($title) . '" />' . "\n";
            }
            echo "<!-- /SNN Social Meta Tags -->\n\n";
        } elseif (is_front_page() || is_home()) {
            // Homepage meta tags
            $title = get_bloginfo('name');
            $description = get_bloginfo('description');
            $url = home_url('/');
            
            // Get site logo
            $image = '';
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $image = $this->get_social_image_url($custom_logo_id);
            }
            
            if (!empty($image)) {
                $image = set_url_scheme($image, 'https');
            }
            
            echo "\n<!-- SNN Social Meta Tags -->\n";
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
            if (!empty($description)) {
                echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
            }
            if (!empty($image)) {
                // WhatsApp requires HTTPS and specific image formats
                $og_image_url = $image;
                
                // If image is WebP, try to get original format for WhatsApp
                $custom_logo_id = get_theme_mod('custom_logo');
                if (strpos($image, '.webp') !== false && $custom_logo_id) {
                    $original_url = $this->convert_webp_to_original($image, $custom_logo_id);
                    if ($original_url && $original_url !== $image) {
                        $og_image_url = $original_url;
                    }
                }
                
                echo '<meta property="og:image" content="' . esc_url($og_image_url) . '" />' . "\n";
                
                // WhatsApp requires og:image:secure_url (HTTPS)
                echo '<meta property="og:image:secure_url" content="' . esc_url($og_image_url) . '" />' . "\n";
                
                // Get actual image dimensions if we have custom logo
                $image_dimensions = false;
                if ($custom_logo_id) {
                    $image_dimensions = $this->get_social_image_dimensions($custom_logo_id, $og_image_url);
                }
                
                // Use actual dimensions if available, otherwise use recommended defaults
                if ($image_dimensions) {
                    echo '<meta property="og:image:width" content="' . esc_attr($image_dimensions['width']) . '" />' . "\n";
                    echo '<meta property="og:image:height" content="' . esc_attr($image_dimensions['height']) . '" />' . "\n";
                } else {
                    // Fallback: use recommended dimensions
                    echo '<meta property="og:image:width" content="1200" />' . "\n";
                    echo '<meta property="og:image:height" content="630" />' . "\n";
                }
                
                // Add og:image:type (WhatsApp prefers JPG/PNG over WebP)
                $image_extension = strtolower(pathinfo(parse_url($og_image_url, PHP_URL_PATH), PATHINFO_EXTENSION));
                if ($image_extension === 'jpg' || $image_extension === 'jpeg') {
                    echo '<meta property="og:image:type" content="image/jpeg" />' . "\n";
                } elseif ($image_extension === 'png') {
                    echo '<meta property="og:image:type" content="image/png" />' . "\n";
                } elseif ($image_extension === 'webp') {
                    echo '<meta property="og:image:type" content="image/webp" />' . "\n";
                }
            }
            echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr($title) . '" />' . "\n";
            echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '" />' . "\n";
            
            // Twitter Card meta tags for homepage
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
            if (!empty($description)) {
                // Twitter description should be max 200 characters
                $twitter_description = mb_substr($description, 0, 200);
                echo '<meta name="twitter:description" content="' . esc_attr($twitter_description) . '" />' . "\n";
            }
            if (!empty($image)) {
                // Use same image URL as Open Graph (with WebP conversion if needed)
                $twitter_image_url = $image;
                $custom_logo_id = get_theme_mod('custom_logo');
                if (strpos($image, '.webp') !== false && $custom_logo_id) {
                    $original_url = $this->convert_webp_to_original($image, $custom_logo_id);
                    if ($original_url && $original_url !== $image) {
                        $twitter_image_url = $original_url;
                    }
                }
                echo '<meta name="twitter:image" content="' . esc_url($twitter_image_url) . '" />' . "\n";
                echo '<meta name="twitter:image:alt" content="' . esc_attr($title) . '" />' . "\n";
            }
            echo "<!-- /SNN Social Meta Tags -->\n\n";
        }
    }
    
    /**
     * Get optimized social image URL
     * Returns image URL optimized for social sharing (1200x630px recommended)
     * IMPORTANT: Converts WebP to JPG/PNG for WhatsApp compatibility
     * WhatsApp does not support WebP well for Open Graph images
     */
    private function get_social_image_url($attachment_id) {
        if (!$attachment_id) {
            return '';
        }
        
        // Get image metadata
        $image_meta = wp_get_attachment_metadata($attachment_id);
        if (!$image_meta) {
            // Fallback to full size if metadata not available
            $image_url = wp_get_attachment_image_url($attachment_id, 'full');
            if ($image_url) {
                $image_url = set_url_scheme($image_url, 'https');
                // Convert WebP to original format for WhatsApp compatibility
                return $this->convert_webp_to_original($image_url, $attachment_id);
            }
            return '';
        }
        
        // Target dimensions for Open Graph (1200x630px)
        $target_width = 1200;
        $target_height = 630;
        
        // Check if image already has optimal size
        $original_width = isset($image_meta['width']) ? $image_meta['width'] : 0;
        $original_height = isset($image_meta['height']) ? $image_meta['height'] : 0;
        
        // If image is already close to target size (within 10%), use it
        if ($original_width >= $target_width * 0.9 && $original_width <= $target_width * 1.5) {
            $image_url = wp_get_attachment_image_url($attachment_id, 'full');
            if ($image_url) {
                $image_url = set_url_scheme($image_url, 'https');
                return $this->convert_webp_to_original($image_url, $attachment_id);
            }
        }
        
        // Try to get the og-image size (1200x630px) - this is registered in register_og_image_size()
        $image_url = wp_get_attachment_image_url($attachment_id, 'og-image');
        if ($image_url) {
            $image_url = set_url_scheme($image_url, 'https');
            return $this->convert_webp_to_original($image_url, $attachment_id);
        }
        
        // Fallback: use large size or full size
        $image_url = wp_get_attachment_image_url($attachment_id, 'large');
        if (!$image_url) {
            $image_url = wp_get_attachment_image_url($attachment_id, 'full');
        }
        
        // Convert to absolute URL with HTTPS
        if ($image_url) {
            $image_url = set_url_scheme($image_url, 'https');
            // Convert WebP to original format for WhatsApp compatibility
            return $this->convert_webp_to_original($image_url, $attachment_id);
        }
        
        return $image_url;
    }
    
    /**
     * Convert WebP URL to original format (JPG/PNG) for WhatsApp compatibility
     * WhatsApp does not support WebP well for Open Graph images
     */
    private function convert_webp_to_original($image_url, $attachment_id = 0) {
        // If URL is not WebP, return as is
        if (strpos($image_url, '.webp') === false) {
            return $image_url;
        }
        
        // Try to get original file path
        if ($attachment_id) {
            $original_file = get_attached_file($attachment_id);
            if ($original_file && file_exists($original_file)) {
                // Get original URL
                $upload_dir = wp_upload_dir();
                $relative_path = str_replace($upload_dir['basedir'], '', $original_file);
                $original_url = $upload_dir['baseurl'] . $relative_path;
                return set_url_scheme($original_url, 'https');
            }
        }
        
        // Fallback: replace .webp with .jpg or .png
        // Try .jpg first (most common)
        $jpg_url = str_replace('.webp', '.jpg', $image_url);
        // If that doesn't work, try .png
        $png_url = str_replace('.webp', '.png', $image_url);
        
        // Return .jpg by default (most compatible with WhatsApp)
        return $jpg_url;
    }
    
    /**
     * Get actual image dimensions for Open Graph meta tags
     * Returns array with 'width' and 'height' or false if unavailable
     */
    private function get_social_image_dimensions($attachment_id, $image_url = '') {
        if ($attachment_id) {
            $image_meta = wp_get_attachment_metadata($attachment_id);
            if ($image_meta && isset($image_meta['width']) && isset($image_meta['height'])) {
                // Use actual dimensions if available
                $width = $image_meta['width'];
                $height = $image_meta['height'];
                
                // If image is very large, scale down to target while maintaining aspect ratio
                $target_width = 1200;
                $target_height = 630;
                
                if ($width > $target_width || $height > $target_height) {
                    $ratio = min($target_width / $width, $target_height / $height);
                    $width = round($width * $ratio);
                    $height = round($height * $ratio);
                }
                
                return array(
                    'width' => $width,
                    'height' => $height
                );
            }
        }
        
        // If we can't get dimensions, return recommended defaults
        // But note: Facebook/WhatsApp will verify actual dimensions when scraping
        return array(
            'width' => 1200,
            'height' => 630
        );
    }
    
    /**
     * Add SEO meta tags (canonical and robots)
     * Only adds if not already added by SEO plugins
     */
    public function add_seo_meta_tags() {
        // Check if SEO plugins are active
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION')) {
            return; // SEO plugins handle this
        }
        
        // Only add canonical on singular posts/pages
        if (is_singular()) {
            global $post;
            if ($post) {
                $canonical_url = get_permalink($post->ID);
                echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";
            }
        } elseif (is_front_page() || is_home()) {
            $canonical_url = home_url('/');
            echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";
        }
        
        // Add robots meta tag if needed (noindex for search, archives, etc.)
        if (is_search() || is_404() || is_date() || is_author()) {
            echo '<meta name="robots" content="noindex, follow" />' . "\n";
        }
    }
    
    /**
     * Fix Bricks Social Sharing widget URLs
     * Corrects share URLs for WhatsApp, Twitter, and other platforms
     * Works with the native Bricks Social Sharing widget
     */
    public function fix_bricks_social_sharing_urls() {
        // Only run on single posts/pages
        if (!is_singular()) {
            return;
        }
        
        $post_id = get_the_ID();
        $post_title = get_the_title($post_id);
        $post_url = get_permalink($post_id);
        $post_excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(get_the_content(), 20);
        
        // Encode values for JavaScript
        // Use rawurlencode for Twitter compatibility (uses %20 instead of + for spaces)
        $encoded_title = esc_js(rawurlencode($post_title));
        $encoded_url = esc_js(rawurlencode($post_url));
        $encoded_text = esc_js(rawurlencode($post_title . ' - ' . $post_excerpt));
        ?>
        <script>
        (function() {
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fixSocialUrls);
            } else {
                fixSocialUrls();
            }
            
            function fixSocialUrls() {
                // Get current page data
                const pageTitle = decodeURIComponent('<?php echo $encoded_title; ?>');
                const pageUrl = decodeURIComponent('<?php echo $encoded_url; ?>');
                const pageText = decodeURIComponent('<?php echo $encoded_text; ?>');
                
                // Find all social sharing links in Bricks Social Sharing widget
                // Bricks uses various selectors, we'll target common ones
                const socialLinks = document.querySelectorAll(
                    'a[href*="facebook.com"], ' +
                    'a[href*="twitter.com"], ' +
                    'a[href*="x.com"], ' +
                    'a[href*="wa.me"], ' +
                    'a[href*="whatsapp.com"], ' +
                    'a[href*="t.me"], ' +
                    'a[href*="mailto:"][href*="subject"]'
                );
                
                socialLinks.forEach(function(link) {
                    const href = link.getAttribute('href');
                    if (!href) return;
                    
                    let newUrl = href;
                    
                    // Fix Facebook - use web sharer
                    if (href.includes('facebook.com/sharer') || href.includes('facebook.com/share')) {
                        newUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(pageUrl);
                    }
                    // Fix Twitter/X - use intent URL (works on mobile and desktop)
                    else if (href.includes('twitter.com') || href.includes('x.com')) {
                        // Remove any existing parameters and rebuild
                        // Use encodeURI instead of encodeURIComponent to preserve spaces as %20 instead of +
                        const encodedTitle = encodeURI(pageTitle).replace(/%20/g, ' ');
                        newUrl = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(pageUrl) + '&text=' + encodeURIComponent(pageTitle);
                    }
                    // Fix WhatsApp - use api.whatsapp.com (works on mobile and desktop)
                    else if (href.includes('wa.me') || href.includes('whatsapp.com')) {
                        const whatsappText = encodeURIComponent(pageTitle + ' ' + pageUrl);
                        newUrl = 'https://api.whatsapp.com/send?text=' + whatsappText;
                    }
                    // Fix Telegram - use web share
                    else if (href.includes('t.me')) {
                        newUrl = 'https://t.me/share/url?url=' + encodeURIComponent(pageUrl) + '&text=' + encodeURIComponent(pageTitle);
                    }
                    // Fix Email - ensure proper formatting
                    else if (href.startsWith('mailto:')) {
                        const emailSubject = encodeURIComponent(pageTitle);
                        const emailBody = encodeURIComponent(pageText + '\n\n' + pageUrl);
                        newUrl = 'mailto:?subject=' + emailSubject + '&body=' + emailBody;
                    }
                    
                    // Update the link if URL changed
                    if (newUrl !== href) {
                        link.setAttribute('href', newUrl);
                        // Also update onclick if present (some widgets use onclick)
                        if (link.getAttribute('onclick')) {
                            link.setAttribute('onclick', "window.open('" + newUrl.replace(/'/g, "\\'") + "', '_blank', 'noopener,noreferrer'); return false;");
                        }
                    }
                });
            }
        })();
        </script>
        <?php
    }
}

// Initialize the class
new SNN_Security_Optimization();
