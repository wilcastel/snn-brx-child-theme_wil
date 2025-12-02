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
        $this->remove_duplicate_meta_tags();
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
    
    /**
     * Remove duplicate meta tags from HTML output
     * This catches duplicates from Bricks Builder scripts, plugins, or custom code
     */
    private function remove_duplicate_meta_tags() {
        // Only run on frontend, not in admin
        if (is_admin()) {
            return;
        }
        
        // Use output buffering to clean HTML before sending to browser
        add_action('template_redirect', function() {
            ob_start(array($this, 'clean_duplicate_meta_tags'));
        }, 1);
    }
    
    /**
     * Clean duplicate meta tags from HTML buffer
     * Removes duplicate meta tags from head section, keeping only the first occurrence
     * This catches duplicates from Bricks Builder scripts, plugins, or custom code
     * 
     * @param string $html The HTML content
     * @return string Cleaned HTML without duplicate meta tags
     */
    public function clean_duplicate_meta_tags($html) {
        // Only process if we have HTML content
        if (empty($html) || strpos($html, '<head') === false) {
            return $html;
        }
        
        // Extract head section
        if (preg_match('/<head[^>]*>(.*?)<\/head>/is', $html, $head_matches)) {
            $head_content = $head_matches[1];
            $head_full_match = $head_matches[0];
            
            // Track which meta tags we've seen (by unique identifier)
            $seen_tags = array();
            
            // Find all meta tags and canonical links in head
            // Match meta tags with name or property attributes
            preg_match_all('/(<meta\s+(?:name|property)=["\']([^"\']+)["\'][^>]*>)/i', $head_content, $meta_matches, PREG_OFFSET_CAPTURE);
            preg_match_all('/(<link\s+rel=["\']canonical["\'][^>]*>)/i', $head_content, $canonical_matches, PREG_OFFSET_CAPTURE);
            
            // Combine all matches with their positions
            $all_tags = array();
            
            // Add meta tags
            if (!empty($meta_matches[0])) {
                foreach ($meta_matches[0] as $index => $match) {
                    $tag_content = $match[0];
                    $tag_offset = $match[1];
                    $tag_attr_value = isset($meta_matches[2][$index][0]) ? $meta_matches[2][$index][0] : '';
                    
                    // Create unique identifier based on attribute type and value
                    if (preg_match('/\s+name=["\']([^"\']+)["\']/i', $tag_content, $name_match)) {
                        $identifier = 'name-' . strtolower($name_match[1]);
                    } elseif (preg_match('/\s+property=["\']([^"\']+)["\']/i', $tag_content, $prop_match)) {
                        $identifier = 'property-' . strtolower($prop_match[1]);
                    } else {
                        $identifier = 'meta-' . md5($tag_content);
                    }
                    
                    $all_tags[] = array(
                        'content' => $tag_content,
                        'offset' => $tag_offset,
                        'identifier' => $identifier,
                        'length' => strlen($tag_content)
                    );
                }
            }
            
            // Add canonical links
            if (!empty($canonical_matches[0])) {
                foreach ($canonical_matches[0] as $match) {
                    $tag_content = $match[0];
                    $tag_offset = $match[1];
                    
                    $all_tags[] = array(
                        'content' => $tag_content,
                        'offset' => $tag_offset,
                        'identifier' => 'canonical',
                        'length' => strlen($tag_content)
                    );
                }
            }
            
            // Sort by offset (reverse order) so we can remove from end to beginning
            usort($all_tags, function($a, $b) {
                return $b['offset'] - $a['offset'];
            });
            
            // Remove duplicates (keep first occurrence, remove subsequent ones)
            foreach ($all_tags as $tag) {
                if (isset($seen_tags[$tag['identifier']])) {
                    // This is a duplicate - remove it
                    // Remove the tag and any trailing whitespace/newline
                    $remove_length = $tag['length'];
                    $after_tag = substr($head_content, $tag['offset'] + $tag['length'], 2);
                    if (preg_match('/^\s+/', $after_tag, $ws_match)) {
                        $remove_length += strlen($ws_match[0]);
                    }
                    
                    $head_content = substr_replace($head_content, '', $tag['offset'], $remove_length);
                } else {
                    // First occurrence - mark as seen
                    $seen_tags[$tag['identifier']] = true;
                }
            }
            
            // Reconstruct head section
            $head_start = preg_match('/<head([^>]*)>/i', $head_full_match, $attr_match) 
                ? '<head' . $attr_match[1] . '>' 
                : '<head>';
            $new_head = $head_start . $head_content . '</head>';
            
            // Replace original head with cleaned version
            $html = str_replace($head_full_match, $new_head, $html);
        }
        
        return $html;
    }
}

// Initialize head optimization
new SNN_Head_Optimization();
