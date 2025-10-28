<?php
/**
 * XML Sitemaps Generator
 * 
 * Generates XML sitemaps for better SEO and Google Search Console integration
 * Optimized for sites with 100,000+ articles
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SNN_XML_Sitemaps_Generator {
    
    private $options;
    private $sitemap_dir;
    private $sitemap_url;
    private $posts_per_page;
    private $max_execution_time;
    
    public function __construct() {
        $this->options = get_option('snn_xml_sitemaps_options', array());
        $this->sitemap_dir = WP_CONTENT_DIR . '/sitemaps/';
        $this->sitemap_url = content_url('sitemaps/');
        $this->posts_per_page = 2000; // Optimized for large sites
        $this->max_execution_time = 300; // 5 minutes max
        
        $this->init_hooks();
        $this->create_sitemap_directory();
        $this->init_auto_update_system();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_snn_generate_sitemap', array($this, 'generate_sitemap_ajax'));
        add_action('wp_ajax_snn_delete_sitemap', array($this, 'delete_sitemap_ajax'));
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_sitemap_request'));
        
        // Auto-update hooks
        add_action('publish_post', array($this, 'handle_new_post'));
        add_action('delete_post', array($this, 'handle_post_deletion'));
        add_action('wp_trash_post', array($this, 'handle_post_deletion'));
        add_action('untrash_post', array($this, 'handle_post_restore'));
    }
    
    /**
     * Create sitemap directory
     */
    private function create_sitemap_directory() {
        if (!file_exists($this->sitemap_dir)) {
            wp_mkdir_p($this->sitemap_dir);
            
            // Add .htaccess for security
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($this->sitemap_dir . '.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Initialize auto-update system
     */
    private function init_auto_update_system() {
        // Check if auto-update is enabled
        if ($this->options['enable_auto_update'] ?? true) {
            // Schedule daily check for sitemap updates
            if (!wp_next_scheduled('snn_sitemap_daily_check')) {
                wp_schedule_event(time(), 'daily', 'snn_sitemap_daily_check');
            }
            
            add_action('snn_sitemap_daily_check', array($this, 'daily_sitemap_check'));
        }
    }
    
    /**
     * Add rewrite rules for sitemap access
     */
    public function add_rewrite_rules() {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?snn_sitemap=main', 'top');
        add_rewrite_rule('^sitemap-([^/]+)\.xml$', 'index.php?snn_sitemap=$matches[1]', 'top');
        add_rewrite_rule('^sitemap-([^/]+)-([0-9]+)\.xml$', 'index.php?snn_sitemap=$matches[1]&snn_sitemap_page=$matches[2]', 'top');
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'snn_sitemap';
        $vars[] = 'snn_sitemap_page';
        return $vars;
    }
    
    /**
     * Handle sitemap request
     */
    public function handle_sitemap_request() {
        $sitemap_type = get_query_var('snn_sitemap');
        
        if ($sitemap_type) {
            $this->serve_sitemap($sitemap_type);
        }
    }
    
    /**
     * Serve sitemap
     */
    private function serve_sitemap($type) {
        // Set proper headers
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');
        
        if ($type === 'main') {
            $this->generate_main_sitemap();
        } else {
            $this->generate_specific_sitemap($type);
        }
        
        exit;
    }
    
    /**
     * Generate main sitemap index
     */
    private function generate_main_sitemap() {
        $sitemaps = $this->get_available_sitemaps();
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($sitemaps as $sitemap) {
            echo '<sitemap>' . "\n";
            
            if (isset($sitemap['page'])) {
                echo '<loc>' . esc_url(home_url('/sitemap-' . $sitemap['type'] . '-' . $sitemap['page'] . '.xml')) . '</loc>' . "\n";
            } else {
                echo '<loc>' . esc_url(home_url('/sitemap-' . $sitemap['type'] . '.xml')) . '</loc>' . "\n";
            }
            
            echo '<lastmod>' . esc_html($sitemap['lastmod']) . '</lastmod>' . "\n";
            echo '</sitemap>' . "\n";
        }
        
        echo '</sitemapindex>';
    }
    
    /**
     * Generate specific sitemap
     */
    private function generate_specific_sitemap($type) {
        $page = intval(get_query_var('snn_sitemap_page')) ?: 1;
        $urls = array();
        
        switch ($type) {
            case 'posts':
                $urls = $this->get_posts_urls($page);
                break;
            case 'pages':
                $urls = $this->get_pages_urls();
                break;
            case 'categories':
                $urls = $this->get_categories_urls();
                break;
            case 'tags':
                $urls = $this->get_tags_urls();
                break;
            case 'authors':
                $urls = $this->get_authors_urls();
                break;
            case 'custom':
                $urls = $this->get_custom_urls();
                break;
        }
        
        $this->output_sitemap($urls);
    }
    
    /**
     * Get posts URLs with pagination support (optimized for large sites)
     */
    private function get_posts_urls($page = 1) {
        $urls = array();
        $offset = ($page - 1) * $this->posts_per_page;
        
        // Use direct database query for better performance on large sites
        global $wpdb;
        
        $posts = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_date, post_modified, post_title
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type = 'post'
            ORDER BY post_date DESC
            LIMIT %d OFFSET %d
        ", $this->posts_per_page, $offset));
        
        foreach ($posts as $post) {
            $urls[] = array(
                'loc' => get_permalink($post->ID),
                'lastmod' => date('c', strtotime($post->post_modified)),
                'changefreq' => $this->get_changefreq($post->post_date),
                'priority' => $this->get_priority($post->post_date, 'post')
            );
        }
        
        return $urls;
    }
    
    /**
     * Handle new post publication
     */
    public function handle_new_post($post_id) {
        if (!$this->options['enable_auto_update'] ?? true) {
            return;
        }
        
        // Only handle posts
        if (get_post_type($post_id) !== 'post') {
            return;
        }
        
        // Schedule sitemap update (defer to avoid blocking)
        wp_schedule_single_event(time() + 30, 'snn_update_sitemap_async');
        add_action('snn_update_sitemap_async', array($this, 'update_sitemap_async'));
    }
    
    /**
     * Handle post deletion
     */
    public function handle_post_deletion($post_id) {
        if (!$this->options['enable_auto_update'] ?? true) {
            return;
        }
        
        // Only handle posts
        if (get_post_type($post_id) !== 'post') {
            return;
        }
        
        // Schedule sitemap update
        wp_schedule_single_event(time() + 30, 'snn_update_sitemap_async');
        add_action('snn_update_sitemap_async', array($this, 'update_sitemap_async'));
    }
    
    /**
     * Handle post restoration
     */
    public function handle_post_restore($post_id) {
        if (!$this->options['enable_auto_update'] ?? true) {
            return;
        }
        
        // Only handle posts
        if (get_post_type($post_id) !== 'post') {
            return;
        }
        
        // Schedule sitemap update
        wp_schedule_single_event(time() + 30, 'snn_update_sitemap_async');
        add_action('snn_update_sitemap_async', array($this, 'update_sitemap_async'));
    }
    
    /**
     * Daily sitemap check
     */
    public function daily_sitemap_check() {
        if (!$this->options['enable_auto_update'] ?? true) {
            return;
        }
        
        // Check if sitemaps need updating
        $last_update = get_option('snn_sitemap_last_update', 0);
        $current_posts_count = $this->get_posts_count();
        $last_posts_count = get_option('snn_sitemap_last_posts_count', 0);
        
        // Update if posts count changed or it's been more than 24 hours
        if ($current_posts_count !== $last_posts_count || (time() - $last_update) > 86400) {
            $this->update_sitemap_async();
        }
    }
    
    /**
     * Async sitemap update
     */
    public function update_sitemap_async() {
        if (!$this->options['enable_auto_update'] ?? true) {
            return;
        }
        
        // Check if we need to update
        $current_posts_count = $this->get_posts_count();
        $last_posts_count = get_option('snn_sitemap_last_posts_count', 0);
        
        if ($current_posts_count !== $last_posts_count) {
            // Update posts sitemaps
            $this->update_posts_sitemaps();
            
            // Update main sitemap index
            $this->update_main_sitemap();
            
            // Update counters
            update_option('snn_sitemap_last_update', time());
            update_option('snn_sitemap_last_posts_count', $current_posts_count);
        }
    }
    
    /**
     * Get total posts count (optimized)
     */
    private function get_posts_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'");
    }
    
    /**
     * Get pages URLs
     */
    private function get_pages_urls() {
        $urls = array();
        $pages = get_posts(array(
            'numberposts' => -1,
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
        
        foreach ($pages as $page) {
            $urls[] = array(
                'loc' => get_permalink($page->ID),
                'lastmod' => get_the_modified_date('c', $page->ID),
                'changefreq' => $this->get_changefreq(get_the_date('Y-m-d', $page->ID)),
                'priority' => $this->get_priority(get_the_date('Y-m-d', $page->ID), 'page')
            );
        }
        
        return $urls;
    }
    
    /**
     * Get categories URLs
     */
    private function get_categories_urls() {
        $urls = array();
        $categories = get_categories(array('hide_empty' => true));
        
        foreach ($categories as $category) {
            $urls[] = array(
                'loc' => get_category_link($category->term_id),
                'lastmod' => date('c'),
                'changefreq' => 'weekly',
                'priority' => '0.6'
            );
        }
        
        return $urls;
    }
    
    /**
     * Get tags URLs
     */
    private function get_tags_urls() {
        $urls = array();
        $tags = get_tags(array('hide_empty' => true));
        
        foreach ($tags as $tag) {
            $urls[] = array(
                'loc' => get_tag_link($tag->term_id),
                'lastmod' => date('c'),
                'changefreq' => 'monthly',
                'priority' => '0.4'
            );
        }
        
        return $urls;
    }
    
    /**
     * Get authors URLs
     */
    private function get_authors_urls() {
        $urls = array();
        $authors = get_users(array('who' => 'authors'));
        
        foreach ($authors as $author) {
            $urls[] = array(
                'loc' => get_author_posts_url($author->ID),
                'lastmod' => date('c'),
                'changefreq' => 'weekly',
                'priority' => '0.5'
            );
        }
        
        return $urls;
    }
    
    /**
     * Get custom URLs
     */
    private function get_custom_urls() {
        $urls = array();
        $custom_urls = $this->options['custom_urls'] ?? '';
        
        if ($custom_urls) {
            $lines = explode("\n", $custom_urls);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line && filter_var($line, FILTER_VALIDATE_URL)) {
                    $urls[] = array(
                        'loc' => $line,
                        'lastmod' => date('c'),
                        'changefreq' => 'monthly',
                        'priority' => '0.5'
                    );
                }
            }
        }
        
        return $urls;
    }
    
    /**
     * Get change frequency
     */
    private function get_changefreq($post_date) {
        $days_old = (time() - strtotime($post_date)) / (60 * 60 * 24);
        
        if ($days_old < 7) {
            return 'daily';
        } elseif ($days_old < 30) {
            return 'weekly';
        } else {
            return 'monthly';
        }
    }
    
    /**
     * Get priority
     */
    private function get_priority($post_date, $type) {
        if ($type === 'page') {
            return '0.8';
        } elseif ($type === 'post') {
            $days_old = (time() - strtotime($post_date)) / (60 * 60 * 24);
            
            if ($days_old < 7) {
                return '0.9';
            } elseif ($days_old < 30) {
                return '0.7';
            } else {
                return '0.5';
            }
        }
        
        return '0.5';
    }
    
    /**
     * Output sitemap XML
     */
    private function output_sitemap($urls) {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($urls as $url) {
            echo '<url>' . "\n";
            echo '<loc>' . esc_url($url['loc']) . '</loc>' . "\n";
            echo '<lastmod>' . esc_html($url['lastmod']) . '</lastmod>' . "\n";
            echo '<changefreq>' . esc_html($url['changefreq']) . '</changefreq>' . "\n";
            echo '<priority>' . esc_html($url['priority']) . '</priority>' . "\n";
            echo '</url>' . "\n";
        }
        
        echo '</urlset>';
    }
    
    /**
     * Get available sitemaps
     */
    private function get_available_sitemaps() {
        $sitemaps = array();
        
        if ($this->options['include_posts'] ?? true) {
            $total_posts = $this->get_posts_count();
            $total_pages = ceil($total_posts / $this->posts_per_page);
            
            for ($i = 1; $i <= $total_pages; $i++) {
                $sitemaps[] = array(
                    'type' => 'posts',
                    'page' => $i,
                    'lastmod' => date('c')
                );
            }
        }
        
        if ($this->options['include_pages'] ?? true) {
            $sitemaps[] = array(
                'type' => 'pages',
                'lastmod' => date('c')
            );
        }
        
        if ($this->options['include_categories'] ?? true) {
            $sitemaps[] = array(
                'type' => 'categories',
                'lastmod' => date('c')
            );
        }
        
        if ($this->options['include_tags'] ?? true) {
            $sitemaps[] = array(
                'type' => 'tags',
                'lastmod' => date('c')
            );
        }
        
        if ($this->options['include_authors'] ?? false) {
            $sitemaps[] = array(
                'type' => 'authors',
                'lastmod' => date('c')
            );
        }
        
        if (!empty($this->options['custom_urls'])) {
            $sitemaps[] = array(
                'type' => 'custom',
                'lastmod' => date('c')
            );
        }
        
        return $sitemaps;
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('snn_xml_sitemaps_options', 'snn_xml_sitemaps_options', array($this, 'sanitize_options'));
        
        add_settings_section(
            'snn_xml_sitemaps_general',
            __('General Settings', 'snn'),
            array($this, 'general_section_callback'),
            'snn-xml-sitemaps'
        );
        
        add_settings_field(
            'enable_sitemaps',
            __('Enable XML Sitemaps', 'snn'),
            array($this, 'enable_sitemaps_callback'),
            'snn-xml-sitemaps',
            'snn_xml_sitemaps_general'
        );
        
        add_settings_field(
            'include_posts',
            __('Include Posts', 'snn'),
            array($this, 'include_posts_callback'),
            'snn-xml-sitemaps',
            'snn_xml_sitemaps_general'
        );
        
        add_settings_field(
            'include_pages',
            __('Include Pages', 'snn'),
            array($this, 'include_pages_callback'),
            'snn-xml-sitemaps',
            'snn_xml_sitemaps_general'
        );
        
        add_settings_field(
            'include_categories',
            __('Include Categories', 'snn'),
            array($this, 'include_categories_callback'),
            'snn-xml-sitemaps',
            'snn_xml_sitemaps_general'
        );
        
        add_settings_field(
            'include_tags',
            __('Include Tags', 'snn'),
            array($this, 'include_tags_callback'),
            'snn-xml-sitemaps',
            'snn_xml_sitemaps_general'
        );
        
        add_settings_field(
            'include_authors',
            __('Include Authors', 'snn'),
            array($this, 'include_authors_callback'),
            'snn-xml-sitemaps',
            'snn_xml_sitemaps_general'
        );
        
        add_settings_field(
            'custom_urls',
            __('Custom URLs', 'snn'),
            array($this, 'custom_urls_callback'),
            'snn-xml-sitemaps',
            'snn_xml_sitemaps_general'
        );
        
        add_settings_field(
            'enable_auto_update',
            __('Enable Auto-Update', 'snn'),
            array($this, 'enable_auto_update_callback'),
            'snn-xml-sitemaps',
            'snn_xml_sitemaps_general'
        );
    }
    
    /**
     * Section callbacks
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure XML sitemap generation and content inclusion.', 'snn') . '</p>';
    }
    
    /**
     * Field callbacks
     */
    public function enable_sitemaps_callback() {
        $enabled = isset($this->options['enable_sitemaps']) ? $this->options['enable_sitemaps'] : true;
        echo '<input type="checkbox" name="snn_xml_sitemaps_options[enable_sitemaps]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Enable XML sitemap generation and serving.', 'snn') . '</p>';
    }
    
    public function include_posts_callback() {
        $enabled = isset($this->options['include_posts']) ? $this->options['include_posts'] : true;
        echo '<input type="checkbox" name="snn_xml_sitemaps_options[include_posts]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Include blog posts in sitemap.', 'snn') . '</p>';
    }
    
    public function include_pages_callback() {
        $enabled = isset($this->options['include_pages']) ? $this->options['include_pages'] : true;
        echo '<input type="checkbox" name="snn_xml_sitemaps_options[include_pages]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Include pages in sitemap.', 'snn') . '</p>';
    }
    
    public function include_categories_callback() {
        $enabled = isset($this->options['include_categories']) ? $this->options['include_categories'] : true;
        echo '<input type="checkbox" name="snn_xml_sitemaps_options[include_categories]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Include category pages in sitemap.', 'snn') . '</p>';
    }
    
    public function include_tags_callback() {
        $enabled = isset($this->options['include_tags']) ? $this->options['include_tags'] : true;
        echo '<input type="checkbox" name="snn_xml_sitemaps_options[include_tags]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Include tag pages in sitemap.', 'snn') . '</p>';
    }
    
    public function include_authors_callback() {
        $enabled = isset($this->options['include_authors']) ? $this->options['include_authors'] : false;
        echo '<input type="checkbox" name="snn_xml_sitemaps_options[include_authors]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Include author pages in sitemap.', 'snn') . '</p>';
    }
    
    public function custom_urls_callback() {
        $urls = $this->options['custom_urls'] ?? '';
        echo '<textarea name="snn_xml_sitemaps_options[custom_urls]" rows="5" cols="50" class="large-text">' . esc_textarea($urls) . '</textarea>';
        echo '<p class="description">' . __('Add custom URLs to include in sitemap (one per line).', 'snn') . '</p>';
    }
    
    public function enable_auto_update_callback() {
        $enabled = isset($this->options['enable_auto_update']) ? $this->options['enable_auto_update'] : true;
        echo '<input type="checkbox" name="snn_xml_sitemaps_options[enable_auto_update]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<p class="description">' . __('Automatically update sitemaps when new posts are published or deleted.', 'snn') . '</p>';
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        $sanitized['enable_sitemaps'] = isset($input['enable_sitemaps']) ? 1 : 0;
        $sanitized['include_posts'] = isset($input['include_posts']) ? 1 : 0;
        $sanitized['include_pages'] = isset($input['include_pages']) ? 1 : 0;
        $sanitized['include_categories'] = isset($input['include_categories']) ? 1 : 0;
        $sanitized['include_tags'] = isset($input['include_tags']) ? 1 : 0;
        $sanitized['include_authors'] = isset($input['include_authors']) ? 1 : 0;
        $sanitized['custom_urls'] = sanitize_textarea_field($input['custom_urls'] ?? '');
        $sanitized['enable_auto_update'] = isset($input['enable_auto_update']) ? 1 : 0;
        
        return $sanitized;
    }
    
    /**
     * Generate sitemap via AJAX
     */
    public function generate_sitemap_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'snn_sitemap_nonce')) {
            wp_die('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Set execution time limit
        set_time_limit($this->max_execution_time);
        
        $start_time = microtime(true);
        
        // Generate physical XML files
        $result = $this->generate_physical_sitemaps();
        
        $processing_time = round(microtime(true) - $start_time, 2);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Sitemap files generated successfully',
                'files_created' => $result['files_created'],
                'total_urls' => $result['total_urls'],
                'processing_time' => $processing_time,
                'sitemap_url' => home_url('/sitemap.xml')
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to generate sitemap files: ' . $result['error']
            ));
        }
    }
    
    /**
     * Generate physical XML sitemap files (optimized for large sites)
     */
    private function generate_physical_sitemaps() {
        try {
            $files_created = 0;
            $total_urls = 0;
            
            // Clear existing sitemap files
            $this->clear_sitemap_files();
            
            // Generate main sitemap index
            $main_sitemap_content = $this->generate_main_sitemap_content();
            if (file_put_contents($this->sitemap_dir . 'sitemap.xml', $main_sitemap_content)) {
                $files_created++;
            }
            
            // Generate posts sitemaps (paginated)
            if ($this->options['include_posts'] ?? true) {
                $posts_result = $this->generate_posts_sitemaps();
                $files_created += $posts_result['files_created'];
                $total_urls += $posts_result['total_urls'];
            }
            
            // Generate other sitemaps
            if ($this->options['include_pages'] ?? true) {
                $pages_result = $this->generate_pages_sitemap();
                $files_created += $pages_result['files_created'];
                $total_urls += $pages_result['total_urls'];
            }
            
            if ($this->options['include_categories'] ?? true) {
                $categories_result = $this->generate_categories_sitemap();
                $files_created += $categories_result['files_created'];
                $total_urls += $categories_result['total_urls'];
            }
            
            if ($this->options['include_tags'] ?? true) {
                $tags_result = $this->generate_tags_sitemap();
                $files_created += $tags_result['files_created'];
                $total_urls += $tags_result['total_urls'];
            }
            
            if ($this->options['include_authors'] ?? false) {
                $authors_result = $this->generate_authors_sitemap();
                $files_created += $authors_result['files_created'];
                $total_urls += $authors_result['total_urls'];
            }
            
            if (!empty($this->options['custom_urls'])) {
                $custom_result = $this->generate_custom_sitemap();
                $files_created += $custom_result['files_created'];
                $total_urls += $custom_result['total_urls'];
            }
            
            return array(
                'success' => true,
                'files_created' => $files_created,
                'total_urls' => $total_urls
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Clear existing sitemap files
     */
    private function clear_sitemap_files() {
        $files = glob($this->sitemap_dir . 'sitemap*.xml');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Generate main sitemap index content
     */
    private function generate_main_sitemap_content() {
        $sitemaps = $this->get_available_sitemaps();
        
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $content .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($sitemaps as $sitemap) {
            $content .= '<sitemap>' . "\n";
            
            if (isset($sitemap['page'])) {
                $content .= '<loc>' . esc_url($this->sitemap_url . 'sitemap-' . $sitemap['type'] . '-' . $sitemap['page'] . '.xml') . '</loc>' . "\n";
            } else {
                $content .= '<loc>' . esc_url($this->sitemap_url . 'sitemap-' . $sitemap['type'] . '.xml') . '</loc>' . "\n";
            }
            
            $content .= '<lastmod>' . esc_html($sitemap['lastmod']) . '</lastmod>' . "\n";
            $content .= '</sitemap>' . "\n";
        }
        
        $content .= '</sitemapindex>';
        
        return $content;
    }
    
    /**
     * Generate posts sitemaps with pagination (optimized for large sites)
     */
    private function generate_posts_sitemaps() {
        $files_created = 0;
        $total_urls = 0;
        $total_posts = $this->get_posts_count();
        $total_pages = ceil($total_posts / $this->posts_per_page);
        
        // Process in batches to avoid memory issues
        for ($page = 1; $page <= $total_pages; $page++) {
            $urls = $this->get_posts_urls($page);
            $content = $this->generate_sitemap_content($urls);
            
            $filename = 'sitemap-posts-' . $page . '.xml';
            if (file_put_contents($this->sitemap_dir . $filename, $content)) {
                $files_created++;
                $total_urls += count($urls);
            }
            
            // Clear memory
            unset($urls, $content);
            
            // Check execution time
            if (time() - $_SERVER['REQUEST_TIME'] > $this->max_execution_time - 30) {
                break; // Stop if we're running out of time
            }
        }
        
        return array(
            'files_created' => $files_created,
            'total_urls' => $total_urls
        );
    }
    
    /**
     * Update posts sitemaps intelligently
     */
    private function update_posts_sitemaps() {
        $total_posts = $this->get_posts_count();
        $total_pages = ceil($total_posts / $this->posts_per_page);
        
        // Get existing sitemap files
        $existing_files = glob($this->sitemap_dir . 'sitemap-posts-*.xml');
        $existing_pages = array();
        
        foreach ($existing_files as $file) {
            if (preg_match('/sitemap-posts-(\d+)\.xml/', basename($file), $matches)) {
                $existing_pages[] = intval($matches[1]);
            }
        }
        
        // Remove extra files if posts decreased
        foreach ($existing_pages as $page) {
            if ($page > $total_pages) {
                $filename = 'sitemap-posts-' . $page . '.xml';
                if (file_exists($this->sitemap_dir . $filename)) {
                    unlink($this->sitemap_dir . $filename);
                }
            }
        }
        
        // Update or create sitemap files
        for ($page = 1; $page <= $total_pages; $page++) {
            $urls = $this->get_posts_urls($page);
            $content = $this->generate_sitemap_content($urls);
            
            $filename = 'sitemap-posts-' . $page . '.xml';
            file_put_contents($this->sitemap_dir . $filename, $content);
            
            // Clear memory
            unset($urls, $content);
        }
    }
    
    /**
     * Update main sitemap index
     */
    private function update_main_sitemap() {
        $content = $this->generate_main_sitemap_content();
        file_put_contents($this->sitemap_dir . 'sitemap.xml', $content);
    }
    
    /**
     * Generate pages sitemap
     */
    private function generate_pages_sitemap() {
        $urls = $this->get_pages_urls();
        $content = $this->generate_sitemap_content($urls);
        
        $filename = 'sitemap-pages.xml';
        $success = file_put_contents($this->sitemap_dir . $filename, $content);
        
        return array(
            'files_created' => $success ? 1 : 0,
            'total_urls' => count($urls)
        );
    }
    
    /**
     * Generate categories sitemap
     */
    private function generate_categories_sitemap() {
        $urls = $this->get_categories_urls();
        $content = $this->generate_sitemap_content($urls);
        
        $filename = 'sitemap-categories.xml';
        $success = file_put_contents($this->sitemap_dir . $filename, $content);
        
        return array(
            'files_created' => $success ? 1 : 0,
            'total_urls' => count($urls)
        );
    }
    
    /**
     * Generate tags sitemap
     */
    private function generate_tags_sitemap() {
        $urls = $this->get_tags_urls();
        $content = $this->generate_sitemap_content($urls);
        
        $filename = 'sitemap-tags.xml';
        $success = file_put_contents($this->sitemap_dir . $filename, $content);
        
        return array(
            'files_created' => $success ? 1 : 0,
            'total_urls' => count($urls)
        );
    }
    
    /**
     * Generate authors sitemap
     */
    private function generate_authors_sitemap() {
        $urls = $this->get_authors_urls();
        $content = $this->generate_sitemap_content($urls);
        
        $filename = 'sitemap-authors.xml';
        $success = file_put_contents($this->sitemap_dir . $filename, $content);
        
        return array(
            'files_created' => $success ? 1 : 0,
            'total_urls' => count($urls)
        );
    }
    
    /**
     * Generate custom sitemap
     */
    private function generate_custom_sitemap() {
        $urls = $this->get_custom_urls();
        $content = $this->generate_sitemap_content($urls);
        
        $filename = 'sitemap-custom.xml';
        $success = file_put_contents($this->sitemap_dir . $filename, $content);
        
        return array(
            'files_created' => $success ? 1 : 0,
            'total_urls' => count($urls)
        );
    }
    
    /**
     * Generate sitemap content from URLs
     */
    private function generate_sitemap_content($urls) {
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($urls as $url) {
            $content .= '<url>' . "\n";
            $content .= '<loc>' . esc_url($url['loc']) . '</loc>' . "\n";
            $content .= '<lastmod>' . esc_html($url['lastmod']) . '</lastmod>' . "\n";
            $content .= '<changefreq>' . esc_html($url['changefreq']) . '</changefreq>' . "\n";
            $content .= '<priority>' . esc_html($url['priority']) . '</priority>' . "\n";
            $content .= '</url>' . "\n";
        }
        
        $content .= '</urlset>';
        
        return $content;
    }
    
    /**
     * Delete sitemap via AJAX
     */
    public function delete_sitemap_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'snn_sitemap_nonce')) {
            wp_die('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Clear physical sitemap files
        $this->clear_sitemap_files();
        
        wp_send_json_success(array('message' => 'Sitemap files deleted successfully'));
    }
}

// Initialize the XML sitemaps generator
new SNN_XML_Sitemaps_Generator();
