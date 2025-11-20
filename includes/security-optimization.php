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
        add_action('wp_enqueue_scripts', array($this, 'conditional_dashicons'));
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
}

// Initialize the class
new SNN_Security_Optimization();
