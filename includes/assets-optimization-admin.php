<?php
/**
 * Assets Optimization Admin Page
 * 
 * Admin interface for assets optimization configuration
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render Assets Optimization admin page
 */
function snn_render_assets_optimization_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    $options = get_option('snn_assets_options', array());
    
    ?>
    <div class="wrap">
        <h1><?php _e('Assets Optimization', 'snn'); ?></h1>
        
        <div class="snn-assets-optimization-admin">
            <div class="snn-assets-settings">
                <h2><?php _e('Optimization Settings', 'snn'); ?></h2>
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields('snn_assets_options');
                    do_settings_sections('snn-assets-optimization');
                    submit_button();
                    ?>
                </form>
            </div>
            
            <div class="snn-assets-info-panel">
                <h2><?php _e('Core Web Vitals Optimization', 'snn'); ?></h2>
                
                <div class="snn-assets-metrics">
                    <h3><?php _e('Performance Metrics', 'snn'); ?></h3>
                    <div class="snn-metrics-grid">
                        <div class="snn-metric-item">
                            <h4><?php _e('LCP (Largest Contentful Paint)', 'snn'); ?></h4>
                            <p><?php _e('Optimized with:', 'snn'); ?></p>
                            <ul>
                                <li><?php _e('Font preloading', 'snn'); ?></li>
                                <li><?php _e('Critical CSS injection', 'snn'); ?></li>
                                <li><?php _e('Resource hints', 'snn'); ?></li>
                                <li><?php _e('Image optimization', 'snn'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="snn-metric-item">
                            <h4><?php _e('CLS (Cumulative Layout Shift)', 'snn'); ?></h4>
                            <p><?php _e('Prevented with:', 'snn'); ?></p>
                            <ul>
                                <li><?php _e('Font-display: swap', 'snn'); ?></li>
                                <li><?php _e('Image dimensions', 'snn'); ?></li>
                                <li><?php _e('Critical CSS', 'snn'); ?></li>
                                <li><?php _e('Stable layouts', 'snn'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="snn-metric-item">
                            <h4><?php _e('FID (First Input Delay)', 'snn'); ?></h4>
                            <p><?php _e('Improved with:', 'snn'); ?></p>
                            <ul>
                                <li><?php _e('JavaScript deferring', 'snn'); ?></li>
                                <li><?php _e('Code splitting', 'snn'); ?></li>
                                <li><?php _e('Third-party optimization', 'snn'); ?></li>
                                <li><?php _e('Console log removal', 'snn'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="snn-assets-features">
                    <h3><?php _e('Optimization Features', 'snn'); ?></h3>
                    <ul>
                        <li><strong><?php _e('Font Optimization:', 'snn'); ?></strong> <?php _e('Preload critical fonts, use font-display: swap, and optimize font loading.', 'snn'); ?></li>
                        <li><strong><?php _e('CSS Optimization:', 'snn'); ?></strong> <?php _e('Critical CSS injection, unused CSS removal, and minification.', 'snn'); ?></li>
                        <li><strong><?php _e('JavaScript Optimization:', 'snn'); ?></strong> <?php _e('Defer non-critical scripts, minification, and console log removal.', 'snn'); ?></li>
                        <li><strong><?php _e('Resource Hints:', 'snn'); ?></strong> <?php _e('DNS prefetch and preconnect for external resources.', 'snn'); ?></li>
                        <li><strong><?php _e('Third-Party Optimization:', 'snn'); ?></strong> <?php _e('Optimized loading for Google Analytics and Facebook Pixel.', 'snn'); ?></li>
                    </ul>
                </div>
                
                <div class="snn-assets-recommendations">
                    <h3><?php _e('Recommendations', 'snn'); ?></h3>
                    <div class="snn-recommendations-grid">
                        <div class="snn-recommendation-item">
                            <h4><?php _e('Font Optimization', 'snn'); ?></h4>
                            <p><?php _e('Add your critical fonts to the preload list. Use WOFF2 format for best performance.', 'snn'); ?></p>
                        </div>
                        
                        <div class="snn-recommendation-item">
                            <h4><?php _e('Critical CSS', 'snn'); ?></h4>
                            <p><?php _e('Extract critical CSS for above-the-fold content and add it to the critical CSS field.', 'snn'); ?></p>
                        </div>
                        
                        <div class="snn-recommendation-item">
                            <h4><?php _e('JavaScript Optimization', 'snn'); ?></h4>
                            <p><?php _e('Ensure critical scripts are not deferred. Non-critical scripts will be automatically deferred.', 'snn'); ?></p>
                        </div>
                        
                        <div class="snn-recommendation-item">
                            <h4><?php _e('Third-Party Scripts', 'snn'); ?></h4>
                            <p><?php _e('Enable third-party optimization for better performance with analytics and tracking scripts.', 'snn'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="snn-assets-tools">
                    <h3><?php _e('Performance Tools', 'snn'); ?></h3>
                    <div class="snn-tools-grid">
                        <div class="snn-tool-item">
                            <h4><?php _e('Google PageSpeed Insights', 'snn'); ?></h4>
                            <p><?php _e('Test your site performance and get specific recommendations.', 'snn'); ?></p>
                            <a href="https://pagespeed.web.dev/" target="_blank" class="button button-secondary">
                                <?php _e('Test Performance', 'snn'); ?>
                            </a>
                        </div>
                        
                        <div class="snn-tool-item">
                            <h4><?php _e('Google Search Console', 'snn'); ?></h4>
                            <p><?php _e('Monitor Core Web Vitals and get performance reports.', 'snn'); ?></p>
                            <a href="https://search.google.com/search-console" target="_blank" class="button button-secondary">
                                <?php _e('Open Search Console', 'snn'); ?>
                            </a>
                        </div>
                        
                        <div class="snn-tool-item">
                            <h4><?php _e('GTmetrix', 'snn'); ?></h4>
                            <p><?php _e('Detailed performance analysis with waterfall charts.', 'snn'); ?></p>
                            <a href="https://gtmetrix.com/" target="_blank" class="button button-secondary">
                                <?php _e('Analyze Performance', 'snn'); ?>
                            </a>
                        </div>
                        
                        <div class="snn-tool-item">
                            <h4><?php _e('WebPageTest', 'snn'); ?></h4>
                            <p><?php _e('Advanced performance testing with multiple locations.', 'snn'); ?></p>
                            <a href="https://www.webpagetest.org/" target="_blank" class="button button-secondary">
                                <?php _e('Test Performance', 'snn'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .snn-assets-optimization-admin {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 20px;
    }
    
    .snn-assets-settings,
    .snn-assets-info-panel {
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    .snn-assets-metrics,
    .snn-assets-features,
    .snn-assets-recommendations,
    .snn-assets-tools {
        margin-bottom: 30px;
    }
    
    .snn-metrics-grid,
    .snn-recommendations-grid,
    .snn-tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 15px;
    }
    
    .snn-metric-item,
    .snn-recommendation-item,
    .snn-tool-item {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        border-left: 4px solid #2271b1;
    }
    
    .snn-metric-item h4,
    .snn-recommendation-item h4,
    .snn-tool-item h4 {
        margin-top: 0;
        color: #2271b1;
    }
    
    .snn-assets-features ul {
        margin-left: 20px;
    }
    
    .snn-assets-features li {
        margin-bottom: 8px;
    }
    
    .snn-tool-item .button {
        margin-top: 10px;
    }
    
    @media (max-width: 768px) {
        .snn-assets-optimization-admin {
            grid-template-columns: 1fr;
        }
        
        .snn-metrics-grid,
        .snn-recommendations-grid,
        .snn-tools-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    <?php
}

/**
 * Add AJAX handlers for assets optimization
 */
add_action('wp_ajax_snn_test_performance', 'snn_test_performance_ajax');
add_action('wp_ajax_snn_generate_critical_css', 'snn_generate_critical_css_ajax');

/**
 * Test performance AJAX handler
 */
function snn_test_performance_ajax() {
    check_ajax_referer('snn_assets_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions.'));
    }
    
    $site_url = home_url();
    $pagespeed_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . urlencode($site_url);
    
    $response = wp_remote_get($pagespeed_url);
    
    if (is_wp_error($response)) {
        wp_send_json_error(__('Failed to test performance.', 'snn'));
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!$data) {
        wp_send_json_error(__('Invalid response from PageSpeed Insights.', 'snn'));
    }
    
    $lcp = $data['lighthouseResult']['audits']['largest-contentful-paint']['displayValue'] ?? 'N/A';
    $cls = $data['lighthouseResult']['audits']['cumulative-layout-shift']['displayValue'] ?? 'N/A';
    $fid = $data['lighthouseResult']['audits']['max-potential-fid']['displayValue'] ?? 'N/A';
    
    wp_send_json_success(array(
        'lcp' => $lcp,
        'cls' => $cls,
        'fid' => $fid,
        'url' => $site_url
    ));
}

/**
 * Generate critical CSS AJAX handler
 */
function snn_generate_critical_css_ajax() {
    check_ajax_referer('snn_assets_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions.'));
    }
    
    $site_url = home_url();
    
    // This would integrate with a critical CSS service
    // For now, we'll provide a basic template
    $critical_css = '/* Critical CSS for ' . $site_url . ' */
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    line-height: 1.6;
    color: #333;
}

.wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Add your critical CSS here */';
    
    wp_send_json_success(array(
        'critical_css' => $critical_css,
        'message' => __('Critical CSS template generated. Please customize it for your site.', 'snn')
    ));
}
