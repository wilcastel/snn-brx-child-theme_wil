<?php
/**
 * Assets Optimization
 * 
 * Advanced optimization for fonts, CSS, and JavaScript to improve Core Web Vitals
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SNN_Assets_Optimization {
    
    private $options;
    private $upload_dir;
    
    public function __construct() {
        $this->upload_dir = wp_upload_dir();
        $this->options = get_option('snn_assets_options', array());
        
        // Initialize settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Apply optimizations
        add_action('wp_enqueue_scripts', array($this, 'apply_font_optimizations'), 1);
        add_action('wp_enqueue_scripts', array($this, 'apply_css_optimizations'), 1);
        add_action('wp_enqueue_scripts', array($this, 'apply_js_optimizations'), 1);
        
        // Critical CSS injection
        add_action('wp_head', array($this, 'inject_critical_css'), 1);
        
        // Font preloading
        add_action('wp_head', array($this, 'preload_critical_fonts'), 1);
        
        // Resource hints
        add_action('wp_head', array($this, 'add_resource_hints'), 1);
        
        // Defer non-critical JavaScript
        add_filter('script_loader_tag', array($this, 'defer_non_critical_js'), 10, 2);
        
        // Remove unused CSS
        add_action('wp_enqueue_scripts', array($this, 'remove_unused_css'), 999);
        
        // Optimize third-party scripts
        add_action('wp_enqueue_scripts', array($this, 'optimize_third_party_scripts'), 999);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'snn_assets_options',
            'snn_assets_options',
            array($this, 'sanitize_options')
        );
        
        add_settings_section(
            'snn_assets_fonts',
            __('Font Optimization', 'snn'),
            array($this, 'fonts_section_callback'),
            'snn-assets-optimization'
        );
        
        add_settings_field(
            'enable_font_preload',
            __('Enable Font Preloading', 'snn'),
            array($this, 'enable_font_preload_callback'),
            'snn-assets-optimization',
            'snn_assets_fonts'
        );
        
        add_settings_field(
            'critical_fonts',
            __('Critical Fonts', 'snn'),
            array($this, 'critical_fonts_callback'),
            'snn-assets-optimization',
            'snn_assets_fonts'
        );
        
        add_settings_field(
            'font_display_swap',
            __('Font Display Swap', 'snn'),
            array($this, 'font_display_swap_callback'),
            'snn-assets-optimization',
            'snn_assets_fonts'
        );
        
        add_settings_section(
            'snn_assets_css',
            __('CSS Optimization', 'snn'),
            array($this, 'css_section_callback'),
            'snn-assets-optimization'
        );
        
        add_settings_field(
            'enable_critical_css',
            __('Enable Critical CSS', 'snn'),
            array($this, 'enable_critical_css_callback'),
            'snn-assets-optimization',
            'snn_assets_css'
        );
        
        add_settings_field(
            'critical_css_content',
            __('Critical CSS Content', 'snn'),
            array($this, 'critical_css_content_callback'),
            'snn-assets-optimization',
            'snn_assets_css'
        );
        
        add_settings_field(
            'remove_unused_css',
            __('Remove Unused CSS', 'snn'),
            array($this, 'remove_unused_css_callback'),
            'snn-assets-optimization',
            'snn_assets_css'
        );
        
        add_settings_field(
            'css_minification',
            __('CSS Minification', 'snn'),
            array($this, 'css_minification_callback'),
            'snn-assets-optimization',
            'snn_assets_css'
        );
        
        add_settings_section(
            'snn_assets_js',
            __('JavaScript Optimization', 'snn'),
            array($this, 'js_section_callback'),
            'snn-assets-optimization'
        );
        
        add_settings_field(
            'defer_non_critical_js',
            __('Defer Non-Critical JS', 'snn'),
            array($this, 'defer_non_critical_js_callback'),
            'snn-assets-optimization',
            'snn_assets_js'
        );
        
        add_settings_field(
            'js_minification',
            __('JavaScript Minification', 'snn'),
            array($this, 'js_minification_callback'),
            'snn-assets-optimization',
            'snn_assets_js'
        );
        
        add_settings_field(
            'remove_console_logs',
            __('Remove Console Logs', 'snn'),
            array($this, 'remove_console_logs_callback'),
            'snn-assets-optimization',
            'snn_assets_js'
        );
        
        add_settings_field(
            'optimize_third_party',
            __('Optimize Third-Party Scripts', 'snn'),
            array($this, 'optimize_third_party_callback'),
            'snn-assets-optimization',
            'snn_assets_js'
        );
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        // Font options
        $sanitized['enable_font_preload'] = isset($input['enable_font_preload']) ? (bool) $input['enable_font_preload'] : true;
        $sanitized['critical_fonts'] = isset($input['critical_fonts']) ? sanitize_textarea_field($input['critical_fonts']) : '';
        $sanitized['font_display_swap'] = isset($input['font_display_swap']) ? (bool) $input['font_display_swap'] : true;
        
        // CSS options
        $sanitized['enable_critical_css'] = isset($input['enable_critical_css']) ? (bool) $input['enable_critical_css'] : true;
        $sanitized['critical_css_content'] = isset($input['critical_css_content']) ? wp_kses_post($input['critical_css_content']) : '';
        $sanitized['remove_unused_css'] = isset($input['remove_unused_css']) ? (bool) $input['remove_unused_css'] : true;
        $sanitized['css_minification'] = isset($input['css_minification']) ? (bool) $input['css_minification'] : true;
        
        // JavaScript options
        $sanitized['defer_non_critical_js'] = isset($input['defer_non_critical_js']) ? (bool) $input['defer_non_critical_js'] : true;
        $sanitized['js_minification'] = isset($input['js_minification']) ? (bool) $input['js_minification'] : true;
        $sanitized['remove_console_logs'] = isset($input['remove_console_logs']) ? (bool) $input['remove_console_logs'] : true;
        $sanitized['optimize_third_party'] = isset($input['optimize_third_party']) ? (bool) $input['optimize_third_party'] : true;
        
        return $sanitized;
    }
    
    /**
     * Apply font optimizations
     */
    public function apply_font_optimizations() {
        if (!($this->options['enable_font_preload'] ?? true)) {
            return;
        }
        
        // Add font-display: swap to all font-face declarations
        if ($this->options['font_display_swap'] ?? true) {
            add_action('wp_head', array($this, 'add_font_display_swap'), 1);
        }
    }
    
    /**
     * Apply CSS optimizations
     */
    public function apply_css_optimizations() {
        // Remove unused CSS
        if ($this->options['remove_unused_css'] ?? true) {
            $this->remove_unused_css();
        }
        
        // Minify CSS
        if ($this->options['css_minification'] ?? true) {
            add_filter('style_loader_src', array($this, 'add_css_minification'), 10, 2);
        }
    }
    
    /**
     * Apply JavaScript optimizations
     */
    public function apply_js_optimizations() {
        // Defer non-critical JavaScript
        if ($this->options['defer_non_critical_js'] ?? true) {
            add_filter('script_loader_tag', array($this, 'defer_non_critical_js'), 10, 2);
        }
        
        // Minify JavaScript
        if ($this->options['js_minification'] ?? true) {
            add_filter('script_loader_src', array($this, 'add_js_minification'), 10, 2);
        }
        
        // Remove console logs
        if ($this->options['remove_console_logs'] ?? true) {
            add_action('wp_head', array($this, 'remove_console_logs'), 999);
        }
    }
    
    /**
     * Inject critical CSS
     */
    public function inject_critical_css() {
        if (!($this->options['enable_critical_css'] ?? true)) {
            return;
        }
        
        $critical_css = $this->options['critical_css_content'] ?? '';
        if (empty($critical_css)) {
            return;
        }
        
        echo '<style id="snn-critical-css">' . $critical_css . '</style>';
    }
    
    /**
     * Preload critical fonts
     */
    public function preload_critical_fonts() {
        if (!($this->options['enable_font_preload'] ?? true)) {
            return;
        }
        
        $critical_fonts = $this->options['critical_fonts'] ?? '';
        if (empty($critical_fonts)) {
            return;
        }
        
        $fonts = explode("\n", $critical_fonts);
        foreach ($fonts as $font) {
            $font = trim($font);
            if (!empty($font)) {
                echo '<link rel="preload" href="' . esc_url($font) . '" as="font" type="font/woff2" crossorigin>';
            }
        }
    }
    
    /**
     * Add resource hints
     */
    public function add_resource_hints() {
        // DNS prefetch for external domains
        $external_domains = array(
            'fonts.googleapis.com',
            'fonts.gstatic.com',
            'cdnjs.cloudflare.com',
            'unpkg.com'
        );
        
        foreach ($external_domains as $domain) {
            echo '<link rel="dns-prefetch" href="//' . $domain . '">';
        }
        
        // Preconnect to critical external resources
        $critical_domains = array(
            'fonts.googleapis.com',
            'fonts.gstatic.com'
        );
        
        foreach ($critical_domains as $domain) {
            echo '<link rel="preconnect" href="https://' . $domain . '" crossorigin>';
        }
    }
    
    /**
     * Add font-display: swap to font-face declarations
     */
    public function add_font_display_swap() {
        echo '<style>
        @font-face {
            font-display: swap;
        }
        </style>';
    }
    
    /**
     * Defer non-critical JavaScript
     */
    public function defer_non_critical_js($tag, $handle) {
        // Critical scripts that should not be deferred
        $critical_scripts = array(
            'jquery',
            'jquery-core',
            'jquery-migrate',
            'bricks-frontend',
            'snn-webp-optimization'
        );
        
        if (in_array($handle, $critical_scripts)) {
            return $tag;
        }
        
        // Defer all other scripts
        return str_replace('<script ', '<script defer ', $tag);
    }
    
    /**
     * Remove unused CSS
     */
    public function remove_unused_css() {
        // Remove unused WordPress CSS
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('global-styles');
        wp_dequeue_style('classic-theme-styles');
        
        // Remove unused Bricks CSS if not in builder
        if (!bricks_is_builder_main()) {
            wp_dequeue_style('bricks-admin');
        }
    }
    
    /**
     * Optimize third-party scripts
     */
    public function optimize_third_party_scripts() {
        if (!($this->options['optimize_third_party'] ?? true)) {
            return;
        }
        
        // Optimize Google Analytics
        add_action('wp_head', array($this, 'optimize_google_analytics'), 1);
        
        // Optimize Facebook Pixel
        add_action('wp_head', array($this, 'optimize_facebook_pixel'), 1);
    }
    
    /**
     * Optimize Google Analytics
     */
    public function optimize_google_analytics() {
        // Only load GA if not already loaded
        if (wp_script_is('google-analytics', 'enqueued')) {
            return;
        }
        
        // Add optimized GA loading
        echo '<script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag("js", new Date());
        gtag("config", "GA_MEASUREMENT_ID", {
            "send_page_view": false
        });
        </script>';
    }
    
    /**
     * Optimize Facebook Pixel
     */
    public function optimize_facebook_pixel() {
        // Only load FB Pixel if not already loaded
        if (wp_script_is('facebook-pixel', 'enqueued')) {
            return;
        }
        
        // Add optimized FB Pixel loading
        echo '<script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,"script",
        "https://connect.facebook.net/en_US/fbevents.js");
        </script>';
    }
    
    /**
     * Remove console logs
     */
    public function remove_console_logs() {
        echo '<script>
        if (typeof console !== "undefined") {
            console.log = function() {};
            console.warn = function() {};
            console.error = function() {};
        }
        </script>';
    }
    
    /**
     * Add CSS minification
     */
    public function add_css_minification($src, $handle) {
        // Add minification parameter
        return add_query_arg('minify', 'true', $src);
    }
    
    /**
     * Add JavaScript minification
     */
    public function add_js_minification($src, $handle) {
        // Add minification parameter
        return add_query_arg('minify', 'true', $src);
    }
    
    // Settings callbacks
    public function fonts_section_callback() {
        echo '<p>' . __('Configure font optimization settings for better Core Web Vitals.', 'snn') . '</p>';
    }
    
    public function css_section_callback() {
        echo '<p>' . __('Configure CSS optimization settings for better performance.', 'snn') . '</p>';
    }
    
    public function js_section_callback() {
        echo '<p>' . __('Configure JavaScript optimization settings for better performance.', 'snn') . '</p>';
    }
    
    public function enable_font_preload_callback() {
        $value = $this->options['enable_font_preload'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[enable_font_preload]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Preload critical fonts for faster rendering.', 'snn') . '</p>';
    }
    
    public function critical_fonts_callback() {
        $value = $this->options['critical_fonts'] ?? '';
        echo '<textarea name="snn_assets_options[critical_fonts]" rows="5" cols="50">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Enter one font URL per line. These fonts will be preloaded.', 'snn') . '</p>';
    }
    
    public function font_display_swap_callback() {
        $value = $this->options['font_display_swap'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[font_display_swap]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Use font-display: swap to prevent invisible text during font load.', 'snn') . '</p>';
    }
    
    public function enable_critical_css_callback() {
        $value = $this->options['enable_critical_css'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[enable_critical_css]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Inject critical CSS inline for faster rendering.', 'snn') . '</p>';
    }
    
    public function critical_css_content_callback() {
        $value = $this->options['critical_css_content'] ?? '';
        echo '<textarea name="snn_assets_options[critical_css_content]" rows="10" cols="50">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Enter critical CSS that should be loaded inline.', 'snn') . '</p>';
    }
    
    public function remove_unused_css_callback() {
        $value = $this->options['remove_unused_css'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[remove_unused_css]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Remove unused CSS from WordPress and plugins.', 'snn') . '</p>';
    }
    
    public function css_minification_callback() {
        $value = $this->options['css_minification'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[css_minification]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Minify CSS files for smaller file sizes.', 'snn') . '</p>';
    }
    
    public function defer_non_critical_js_callback() {
        $value = $this->options['defer_non_critical_js'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[defer_non_critical_js]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Defer non-critical JavaScript for faster page load.', 'snn') . '</p>';
    }
    
    public function js_minification_callback() {
        $value = $this->options['js_minification'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[js_minification]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Minify JavaScript files for smaller file sizes.', 'snn') . '</p>';
    }
    
    public function remove_console_logs_callback() {
        $value = $this->options['remove_console_logs'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[remove_console_logs]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Remove console.log statements from production.', 'snn') . '</p>';
    }
    
    public function optimize_third_party_callback() {
        $value = $this->options['optimize_third_party'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[optimize_third_party]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Optimize third-party scripts like Google Analytics and Facebook Pixel.', 'snn') . '</p>';
    }
}

// Initialize the class
new SNN_Assets_Optimization();
