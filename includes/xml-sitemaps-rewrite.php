<?php
/**
 * XML Sitemaps Rewrite Rules
 * 
 * Handles rewrite rules for XML sitemaps
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Flush rewrite rules on activation
 */
function snn_xml_sitemaps_flush_rewrite_rules() {
    // Add rewrite rules
    add_rewrite_rule('^sitemap\.xml$', 'index.php?snn_sitemap=main', 'top');
    add_rewrite_rule('^sitemap-([^/]+)\.xml$', 'index.php?snn_sitemap=$matches[1]', 'top');
    add_rewrite_rule('^sitemap-([^/]+)-([0-9]+)\.xml$', 'index.php?snn_sitemap=$matches[1]&snn_sitemap_page=$matches[2]', 'top');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Add rewrite rules on init
 */
function snn_xml_sitemaps_add_rewrite_rules() {
    add_rewrite_rule('^sitemap\.xml$', 'index.php?snn_sitemap=main', 'top');
    add_rewrite_rule('^sitemap-([^/]+)\.xml$', 'index.php?snn_sitemap=$matches[1]', 'top');
    add_rewrite_rule('^sitemap-([^/]+)-([0-9]+)\.xml$', 'index.php?snn_sitemap=$matches[1]&snn_sitemap_page=$matches[2]', 'top');
}
add_action('init', 'snn_xml_sitemaps_add_rewrite_rules');

/**
 * Add query vars
 */
function snn_xml_sitemaps_add_query_vars($vars) {
    $vars[] = 'snn_sitemap';
    $vars[] = 'snn_sitemap_page';
    return $vars;
}
add_filter('query_vars', 'snn_xml_sitemaps_add_query_vars');

/**
 * Handle sitemap request
 */
function snn_xml_sitemaps_handle_request() {
    $sitemap_type = get_query_var('snn_sitemap');
    
    if ($sitemap_type) {
        // Set proper headers
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');
        
        if ($sitemap_type === 'main') {
            snn_xml_sitemaps_generate_main_sitemap();
        } else {
            snn_xml_sitemaps_generate_specific_sitemap($sitemap_type);
        }
        
        exit;
    }
}
add_action('template_redirect', 'snn_xml_sitemaps_handle_request');

/**
 * Generate main sitemap index
 */
function snn_xml_sitemaps_generate_main_sitemap() {
    $sitemaps = snn_xml_sitemaps_get_available_sitemaps();
    
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
function snn_xml_sitemaps_generate_specific_sitemap($type) {
    $page = intval(get_query_var('snn_sitemap_page')) ?: 1;
    $urls = array();
    
    switch ($type) {
        case 'posts':
            $urls = snn_xml_sitemaps_get_posts_urls($page);
            break;
        case 'pages':
            $urls = snn_xml_sitemaps_get_pages_urls();
            break;
        case 'categories':
            $urls = snn_xml_sitemaps_get_categories_urls();
            break;
        case 'tags':
            $urls = snn_xml_sitemaps_get_tags_urls();
            break;
        case 'authors':
            $urls = snn_xml_sitemaps_get_authors_urls();
            break;
        case 'custom':
            $urls = snn_xml_sitemaps_get_custom_urls();
            break;
    }
    
    snn_xml_sitemaps_output_sitemap($urls);
}

/**
 * Get available sitemaps
 */
function snn_xml_sitemaps_get_available_sitemaps() {
    $options = get_option('snn_xml_sitemaps_options', array());
    $sitemaps = array();
    
    if ($options['include_posts'] ?? true) {
        $total_posts = snn_xml_sitemaps_get_posts_count();
        $posts_per_page = 2000;
        $total_pages = ceil($total_posts / $posts_per_page);
        
        for ($i = 1; $i <= $total_pages; $i++) {
            $sitemaps[] = array(
                'type' => 'posts',
                'page' => $i,
                'lastmod' => date('c')
            );
        }
    }
    
    if ($options['include_pages'] ?? true) {
        $sitemaps[] = array(
            'type' => 'pages',
            'lastmod' => date('c')
        );
    }
    
    if ($options['include_categories'] ?? true) {
        $sitemaps[] = array(
            'type' => 'categories',
            'lastmod' => date('c')
        );
    }
    
    if ($options['include_tags'] ?? true) {
        $sitemaps[] = array(
            'type' => 'tags',
            'lastmod' => date('c')
        );
    }
    
    if ($options['include_authors'] ?? false) {
        $sitemaps[] = array(
            'type' => 'authors',
            'lastmod' => date('c')
        );
    }
    
    if (!empty($options['custom_urls'])) {
        $sitemaps[] = array(
            'type' => 'custom',
            'lastmod' => date('c')
        );
    }
    
    return $sitemaps;
}

/**
 * Get posts count
 */
function snn_xml_sitemaps_get_posts_count() {
    global $wpdb;
    return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'");
}

/**
 * Get posts URLs
 */
function snn_xml_sitemaps_get_posts_urls($page = 1) {
    $urls = array();
    $posts_per_page = 2000;
    $offset = ($page - 1) * $posts_per_page;
    
    global $wpdb;
    
    $posts = $wpdb->get_results($wpdb->prepare("
        SELECT ID, post_date, post_modified, post_title
        FROM {$wpdb->posts}
        WHERE post_status = 'publish'
        AND post_type = 'post'
        ORDER BY post_date DESC
        LIMIT %d OFFSET %d
    ", $posts_per_page, $offset));
    
    foreach ($posts as $post) {
        $urls[] = array(
            'loc' => get_permalink($post->ID),
            'lastmod' => date('c', strtotime($post->post_modified)),
            'changefreq' => snn_xml_sitemaps_get_changefreq($post->post_date),
            'priority' => snn_xml_sitemaps_get_priority($post->post_date, 'post')
        );
    }
    
    return $urls;
}

/**
 * Get pages URLs
 */
function snn_xml_sitemaps_get_pages_urls() {
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
            'changefreq' => snn_xml_sitemaps_get_changefreq(get_the_date('Y-m-d', $page->ID)),
            'priority' => snn_xml_sitemaps_get_priority(get_the_date('Y-m-d', $page->ID), 'page')
        );
    }
    
    return $urls;
}

/**
 * Get categories URLs
 */
function snn_xml_sitemaps_get_categories_urls() {
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
function snn_xml_sitemaps_get_tags_urls() {
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
function snn_xml_sitemaps_get_authors_urls() {
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
function snn_xml_sitemaps_get_custom_urls() {
    $urls = array();
    $options = get_option('snn_xml_sitemaps_options', array());
    $custom_urls = $options['custom_urls'] ?? '';
    
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
function snn_xml_sitemaps_get_changefreq($post_date) {
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
function snn_xml_sitemaps_get_priority($post_date, $type) {
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
function snn_xml_sitemaps_output_sitemap($urls) {
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
