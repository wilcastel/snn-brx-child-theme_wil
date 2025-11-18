<?php
/**
 * CSS Request Tracker
 * 
 * Tracks all CSS requests to identify what's generating the invalid Cloudflare PageSpeed URLs
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CSS Request Tracker Class
 */
class SNN_CSS_Request_Tracker {
    
    private $tracked_styles = array();
    private $log_file;
    
    public function __construct() {
        // Only run if WP_DEBUG is enabled or if explicitly enabled via constant
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            // Check if tracking is explicitly enabled
            if (!defined('SNN_TRACK_CSS_REQUESTS') || !SNN_TRACK_CSS_REQUESTS) {
                return;
            }
        }
        
        $this->log_file = WP_CONTENT_DIR . '/css-requests.log';
        
        // Track style enqueues
        add_filter('style_loader_src', array($this, 'track_style_src'), 9999, 2);
        
        // Track style tags output
        add_filter('style_loader_tag', array($this, 'track_style_tag'), 9999, 2);
        
        // Log at shutdown to capture everything
        add_action('shutdown', array($this, 'log_tracked_styles'), 9999);
        
        // Also log on wp_head to catch early requests
        add_action('wp_head', array($this, 'log_current_styles'), 999);
    }
    
    /**
     * Track style source URL
     */
    public function track_style_src($src, $handle) {
        if (empty($src)) {
            return $src;
        }
        
        // Check if this is the problematic file
        $is_problematic = (
            strpos($src, 'A.style.min.css') !== false ||
            strpos($src, 'pagespeed.cf.') !== false ||
            strpos($src, '/wp-includes/css/dist/block-library/') !== false
        );
        
        // Get call stack
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        $this->tracked_styles[$handle] = array(
            'handle' => $handle,
            'src' => $src,
            'is_problematic' => $is_problematic,
            'timestamp' => microtime(true),
            'backtrace' => $this->format_backtrace($backtrace),
            'is_enqueued_by' => $this->get_enqueue_source($backtrace)
        );
        
        // If problematic, log immediately
        if ($is_problematic) {
            $this->log_problematic_style($handle, $src, $backtrace);
        }
        
        return $src;
    }
    
    /**
     * Track style tag output
     */
    public function track_style_tag($tag, $handle) {
        if (empty($tag)) {
            return $tag;
        }
        
        // Extract URL from tag
        if (preg_match('/href=["\']([^"\']+)["\']/', $tag, $matches)) {
            $url = $matches[1];
            
            // Check if this is the problematic URL
            if (strpos($url, 'pagespeed.cf.') !== false) {
                $this->log_problematic_tag($handle, $url, $tag);
            }
        }
        
        return $tag;
    }
    
    /**
     * Get source of enqueue
     */
    private function get_enqueue_source($backtrace) {
        foreach ($backtrace as $trace) {
            if (isset($trace['function'])) {
                if (in_array($trace['function'], array('wp_enqueue_style', 'wp_register_style'))) {
                    return array(
                        'file' => $trace['file'] ?? 'unknown',
                        'line' => $trace['line'] ?? 'unknown',
                        'function' => $trace['function'] ?? 'unknown'
                    );
                }
            }
        }
        return 'unknown';
    }
    
    /**
     * Format backtrace for logging
     */
    private function format_backtrace($backtrace) {
        $formatted = array();
        foreach ($backtrace as $index => $trace) {
            if ($index > 5) break; // Limit to first 5 calls
            $formatted[] = array(
                'file' => isset($trace['file']) ? basename($trace['file']) : 'unknown',
                'line' => $trace['line'] ?? 'unknown',
                'function' => $trace['function'] ?? 'unknown',
                'class' => $trace['class'] ?? null
            );
        }
        return $formatted;
    }
    
    /**
     * Log problematic style immediately
     */
    private function log_problematic_style($handle, $src, $backtrace) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'type' => 'PROBLEMATIC_CSS_SRC',
            'handle' => $handle,
            'src' => $src,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'is_admin' => is_admin(),
            'is_frontend' => !is_admin(),
            'backtrace' => $this->format_backtrace($backtrace),
            'registered_styles' => $this->get_all_registered_styles()
        );
        
        error_log('SNN CSS Tracker - PROBLEMATIC: ' . print_r($log_entry, true));
        
        // Also write to file
        file_put_contents(
            $this->log_file,
            "\n" . date('Y-m-d H:i:s') . " - PROBLEMATIC CSS SRC\n" . 
            print_r($log_entry, true) . "\n" . str_repeat('=', 80) . "\n",
            FILE_APPEND
        );
    }
    
    /**
     * Log problematic style tag
     */
    private function log_problematic_tag($handle, $url, $tag) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'type' => 'PROBLEMATIC_CSS_TAG',
            'handle' => $handle,
            'url' => $url,
            'tag' => $tag,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'is_admin' => is_admin(),
            'is_frontend' => !is_frontend()
        );
        
        error_log('SNN CSS Tracker - PROBLEMATIC TAG: ' . print_r($log_entry, true));
        
        file_put_contents(
            $this->log_file,
            "\n" . date('Y-m-d H:i:s') . " - PROBLEMATIC CSS TAG\n" . 
            print_r($log_entry, true) . "\n" . str_repeat('=', 80) . "\n",
            FILE_APPEND
        );
    }
    
    /**
     * Get all registered styles
     */
    private function get_all_registered_styles() {
        global $wp_styles;
        if (!isset($wp_styles) || !is_object($wp_styles)) {
            return array();
        }
        
        $registered = array();
        foreach ($wp_styles->registered as $handle => $style) {
            $registered[$handle] = array(
                'src' => $style->src ?? 'unknown',
                'deps' => $style->deps ?? array(),
                'ver' => $style->ver ?? 'unknown',
                'media' => $style->args ?? 'all'
            );
        }
        
        return $registered;
    }
    
    /**
     * Log current styles at wp_head
     */
    public function log_current_styles() {
        global $wp_styles;
        
        if (!isset($wp_styles) || !is_object($wp_styles)) {
            return;
        }
        
        $queued = array();
        foreach ($wp_styles->queue as $handle) {
            if (isset($wp_styles->registered[$handle])) {
                $style = $wp_styles->registered[$handle];
                $queued[$handle] = array(
                    'src' => $style->src ?? 'unknown',
                    'deps' => $style->deps ?? array(),
                    'ver' => $style->ver ?? 'unknown'
                );
                
                // Check if this is the problematic file
                if (isset($style->src) && strpos($style->src, 'A.style.min.css') !== false) {
                    error_log("SNN CSS Tracker - Found A.style.min.css in queue: Handle = $handle, Src = " . $style->src);
                }
            }
        }
        
        if (!empty($queued)) {
            error_log('SNN CSS Tracker - All queued styles at wp_head: ' . print_r($queued, true));
        }
    }
    
    /**
     * Log all tracked styles at shutdown
     */
    public function log_tracked_styles() {
        if (empty($this->tracked_styles)) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'page_type' => $this->get_page_type(),
            'tracked_styles' => $this->tracked_styles,
            'all_registered_styles' => $this->get_all_registered_styles()
        );
        
        error_log('SNN CSS Tracker - All tracked styles: ' . print_r($log_entry, true));
        
        file_put_contents(
            $this->log_file,
            "\n" . date('Y-m-d H:i:s') . " - ALL TRACKED STYLES\n" . 
            print_r($log_entry, true) . "\n" . str_repeat('=', 80) . "\n",
            FILE_APPEND
        );
    }
    
    /**
     * Get page type
     */
    private function get_page_type() {
        if (is_admin()) {
            return 'admin';
        } elseif (is_front_page()) {
            return 'front_page';
        } elseif (is_single()) {
            return 'single';
        } elseif (is_page()) {
            return 'page';
        } elseif (is_archive()) {
            return 'archive';
        } else {
            return 'other';
        }
    }
}

// Initialize tracker if WP_DEBUG is enabled or if explicitly enabled
// To enable: define('SNN_TRACK_CSS_REQUESTS', true); in wp-config.php
if (defined('WP_DEBUG') && WP_DEBUG) {
    new SNN_CSS_Request_Tracker();
} elseif (defined('SNN_TRACK_CSS_REQUESTS') && SNN_TRACK_CSS_REQUESTS) {
    new SNN_CSS_Request_Tracker();
}




