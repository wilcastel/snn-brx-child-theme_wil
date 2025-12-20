<?php
/**
 * Head Optimization for SNN-BRX-WIL
 * 
 * This file contains optimizations that are always active to improve
 * Core Web Vitals and reduce head bloat.
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Head Optimization Class
 */
class SNN_Head_Optimization {
    
    public function __construct() {
        add_action('init', array($this, 'init_optimizations'));
    }
    
    /**
     * Initialize all head optimizations
     */
    public function init_optimizations() {
        // Always active optimizations
        $this->remove_unnecessary_head_elements();
        $this->optimize_css_loading();
        $this->optimize_scripts();
        $this->optimize_windpress();
    }
    
    /**
     * Remove unnecessary head elements
     */
    private function remove_unnecessary_head_elements() {
        // Remove WordPress generator
        remove_action('wp_head', 'wp_generator');
        
        // Remove RSD link
        remove_action('wp_head', 'rsd_link');
        
        // Remove WLW manifest
        remove_action('wp_head', 'wlwmanifest_link');
        
        // Remove shortlink
        remove_action('wp_head', 'wp_shortlink_wp_head');
        
        // Remove REST API links
        remove_action('wp_head', 'rest_output_link_wp_head');
        
        // Remove OEmbed discovery links
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        
        // Remove DNS prefetch
        remove_action('wp_head', 'wp_resource_hints', 2);
        
        // Remove WordPress version from RSS feeds
        add_filter('the_generator', '__return_empty_string');
    }
    
    /**
     * Optimize CSS loading
     */
    private function optimize_css_loading() {
        // Limit inline CSS size
        add_filter('styles_inline_size_limit', function() {
            return 0; // Force external files
        });
        
        // Remove duplicate CSS loading
        add_action('wp_enqueue_scripts', array($this, 'prevent_duplicate_css'), 20);
        
        // Conditional Dashicons loading
        add_action('wp_enqueue_scripts', array($this, 'conditional_dashicons'), 20);
        
        // Remove admin bar CSS for non-admin users
        if (!current_user_can('administrator')) {
            add_filter('show_admin_bar', '__return_false');
        }
    }
    
    /**
     * Optimize scripts
     */
    private function optimize_scripts() {
        // Remove jQuery migrate
        add_action('wp_default_scripts', array($this, 'remove_jquery_migrate'));
        
        // Defer non-critical scripts
        add_filter('script_loader_tag', array($this, 'defer_non_critical_scripts'), 10, 2);
    }
    
    /**
     * Optimize WindPress
     */
    private function optimize_windpress() {
        // Disable WindPress debug mode in production
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            add_filter('windpress_debug_mode', '__return_false');
        }
        
        // Optimize WindPress CSS delivery
        add_filter('windpress_css_delivery', array($this, 'optimize_windpress_css'));
    }
    
    /**
     * Prevent duplicate CSS loading
     */
    public function prevent_duplicate_css() {
        // Add script to remove duplicate preload links
        add_action('wp_head', function() {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Remove duplicate preload links
                var preloadLinks = document.querySelectorAll('link[rel="preload"][as="style"]');
                var stylesheetLinks = document.querySelectorAll('link[rel="stylesheet"]');
                
                preloadLinks.forEach(function(preload) {
                    var preloadHref = preload.href;
                    stylesheetLinks.forEach(function(stylesheet) {
                        if (stylesheet.href === preloadHref) {
                            preload.remove();
                        }
                    });
                });
                
                // Remove empty style tags
                var styleTags = document.querySelectorAll('style');
                styleTags.forEach(function(style) {
                    if (!style.textContent.trim()) {
                        style.remove();
                    }
                });
            });
            </script>
            <?php
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
     * Remove jQuery migrate
     */
    public function remove_jquery_migrate($scripts) {
        if (!is_admin() && isset($scripts->registered['jquery'])) {
            $script = $scripts->registered['jquery'];
            if ($script->deps) {
                $script->deps = array_diff($script->deps, array('jquery-migrate'));
            }
        }
    }
    
    /**
     * Defer non-critical scripts
     */
    public function defer_non_critical_scripts($tag, $handle) {
        // Scripts to defer
        $defer_scripts = array(
            'jquery',
            'wp-embed',
            'comment-reply'
        );
        
        if (in_array($handle, $defer_scripts)) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Optimize WindPress CSS delivery
     */
    public function optimize_windpress_css($css) {
        // Minify CSS if not already minified
        if (strpos($css, '/*') !== false) {
            $css = preg_replace('/\/\*.*?\*\//s', '', $css);
            $css = preg_replace('/\s+/', ' ', $css);
            $css = trim($css);
        }
        
        return $css;
    }
}

// Initialize head optimization
new SNN_Head_Optimization();
