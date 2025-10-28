<?php
/**
 * XML Sitemaps Admin Page
 * 
 * Admin interface for XML sitemaps configuration
 * Optimized for sites with 100,000+ articles
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render XML Sitemaps admin page
 */
function snn_render_xml_sitemaps_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Debug: Check if function is being called
    error_log('snn_render_xml_sitemaps_page function called');
    
    $options = get_option('snn_xml_sitemaps_options', array());
    $sitemap_url = home_url('/sitemap.xml');
    $sitemap_exists = true; // Dynamic sitemap, always exists when enabled
    
    // Get current stats
    $total_posts = wp_count_posts('post')->publish;
    $total_pages = wp_count_posts('page')->publish;
    $total_categories = wp_count_terms('category');
    $total_tags = wp_count_terms('post_tag');
    
    ?>
    <div class="wrap">
        <h1><?php _e('XML Sitemaps', 'snn'); ?></h1>
        
        <div class="snn-xml-sitemaps-admin">
            <div class="snn-sitemap-actions">
                <h2><?php _e('Sitemap Management', 'snn'); ?></h2>
                
                <div class="snn-sitemap-info">
                    <h3><?php _e('Sitemap URL:', 'snn'); ?></h3>
                    <p><code><?php echo esc_url($sitemap_url); ?></code></p>
                    <p class="description"><?php _e('Submit this URL to Google Search Console for better SEO.', 'snn'); ?></p>
                    
                    <h3><?php _e('Physical Files Location:', 'snn'); ?></h3>
                    <p><code>/wp-content/sitemaps/</code></p>
                    <p class="description"><?php _e('XML files are created physically in this directory, optimized for large sites.', 'snn'); ?></p>
                </div>
                
                <div class="snn-sitemap-stats">
                    <h3><?php _e('Current Content Stats:', 'snn'); ?></h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($total_posts); ?></span>
                            <span class="stat-label"><?php _e('Posts', 'snn'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($total_pages); ?></span>
                            <span class="stat-label"><?php _e('Pages', 'snn'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($total_categories); ?></span>
                            <span class="stat-label"><?php _e('Categories', 'snn'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($total_tags); ?></span>
                            <span class="stat-label"><?php _e('Tags', 'snn'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="snn-sitemap-actions-buttons">
                    <button id="generate-sitemap" class="button button-primary">
                        <?php _e('Generate/Update Sitemap', 'snn'); ?>
                    </button>
                    
                    <button id="view-sitemap" class="button button-secondary" onclick="window.open('<?php echo esc_url($sitemap_url); ?>', '_blank')">
                        <?php _e('View Sitemap', 'snn'); ?>
                    </button>
                    
                    <button id="submit-to-google" class="button button-secondary" onclick="window.open('https://search.google.com/search-console', '_blank')">
                        <?php _e('Submit to Google Search Console', 'snn'); ?>
                    </button>
                    
                    <button id="delete-sitemap" class="button button-secondary" style="color: #d63638;">
                        <?php _e('Delete All Sitemaps', 'snn'); ?>
                    </button>
                </div>
                
                <div id="sitemap-results" class="snn-sitemap-results" style="display: none;">
                    <!-- Results will be shown here -->
                </div>
            </div>
            
            <div class="snn-sitemap-settings">
                <h2><?php _e('Settings', 'snn'); ?></h2>
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields('snn_xml_sitemaps_options');
                    do_settings_sections('snn-xml-sitemaps');
                    submit_button();
                    ?>
                </form>
            </div>
            
            <div class="snn-sitemap-info-panel">
                <h2><?php _e('About XML Sitemaps', 'snn'); ?></h2>
                <p><?php _e('XML sitemaps help search engines discover and index your content more efficiently.', 'snn'); ?></p>
                
                <h3><?php _e('Features:', 'snn'); ?></h3>
                <ul>
                    <li><?php _e('Automatic sitemap generation', 'snn'); ?></li>
                    <li><?php _e('Multiple content types support', 'snn'); ?></li>
                    <li><?php _e('Custom URLs inclusion', 'snn'); ?></li>
                    <li><?php _e('SEO-optimized priorities and frequencies', 'snn'); ?></li>
                    <li><?php _e('Google Search Console compatible', 'snn'); ?></li>
                    <li><?php _e('Optimized for large sites (100,000+ articles)', 'snn'); ?></li>
                </ul>
                
                <h3><?php _e('Sitemap Structure:', 'snn'); ?></h3>
                <ul>
                    <li><strong><?php _e('Main Sitemap:', 'snn'); ?></strong> <code>/sitemap.xml</code></li>
                    <li><strong><?php _e('Posts Sitemaps:', 'snn'); ?></strong> <code>/sitemap-posts-1.xml</code>, <code>/sitemap-posts-2.xml</code>, etc.</li>
                    <li><strong><?php _e('Pages Sitemap:', 'snn'); ?></strong> <code>/sitemap-pages.xml</code></li>
                    <li><strong><?php _e('Categories Sitemap:', 'snn'); ?></strong> <code>/sitemap-categories.xml</code></li>
                    <li><strong><?php _e('Tags Sitemap:', 'snn'); ?></strong> <code>/sitemap-tags.xml</code></li>
                </ul>
                
                <h3><?php _e('Smart Auto-Update System:', 'snn'); ?></h3>
                <ul>
                    <li><?php _e('Automatically detects new posts and updates sitemaps', 'snn'); ?></li>
                    <li><?php _e('Creates new files when reaching 2,000 posts per file', 'snn'); ?></li>
                    <li><?php _e('Removes extra files when posts are deleted', 'snn'); ?></li>
                    <li><?php _e('Updates main sitemap index automatically', 'snn'); ?></li>
                    <li><?php _e('Daily check to ensure sitemaps are current', 'snn'); ?></li>
                    <li><?php _e('Intelligent batching for large sites', 'snn'); ?></li>
                </ul>
                
                <h3><?php _e('Physical Files System (Like Jetpack):', 'snn'); ?></h3>
                <ul>
                    <li><?php _e('Creates actual XML files in /wp-content/sitemaps/', 'snn'); ?></li>
                    <li><?php _e('Files are optimized to stay under 2MB each', 'snn'); ?></li>
                    <li><?php _e('Posts are split into multiple files (2,000 posts each)', 'snn'); ?></li>
                    <li><?php _e('Perfect for Google Search Console submission', 'snn'); ?></li>
                    <li><?php _e('No dynamic generation - pure static files', 'snn'); ?></li>
                    <li><?php _e('Memory-efficient processing for large datasets', 'snn'); ?></li>
                </ul>
                
                <h3><?php _e('SEO Benefits:', 'snn'); ?></h3>
                <ul>
                    <li><?php _e('Faster content discovery', 'snn'); ?></li>
                    <li><?php _e('Better search engine indexing', 'snn'); ?></li>
                    <li><?php _e('Improved crawl efficiency', 'snn'); ?></li>
                    <li><?php _e('Enhanced search visibility', 'snn'); ?></li>
                    <li><?php _e('Optimized for Core Web Vitals', 'snn'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <style>
    .snn-xml-sitemaps-admin {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 20px;
    }
    
    .snn-sitemap-actions,
    .snn-sitemap-settings,
    .snn-sitemap-info-panel {
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    .snn-sitemap-info {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .snn-sitemap-info code {
        background: #fff;
        padding: 5px 10px;
        border-radius: 3px;
        font-family: monospace;
        font-size: 14px;
    }
    
    .snn-sitemap-stats {
        background: #f0f8ff;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        border-left: 4px solid #2271b1;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }
    
    .stat-item {
        text-align: center;
        padding: 10px;
        background: #fff;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
    
    .stat-number {
        display: block;
        font-size: 24px;
        font-weight: bold;
        color: #2271b1;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .snn-sitemap-actions-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    
    .snn-sitemap-results {
        background: #f0f8ff;
        padding: 15px;
        border-radius: 4px;
        border-left: 4px solid #2271b1;
    }
    
    .snn-sitemap-info-panel ul {
        margin-left: 20px;
    }
    
    .snn-sitemap-info-panel li {
        margin-bottom: 8px;
    }
    
    @media (max-width: 768px) {
        .snn-xml-sitemaps-admin {
            grid-template-columns: 1fr;
        }
        
        .snn-sitemap-actions-buttons {
            flex-direction: column;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#generate-sitemap').on('click', function() {
            const button = $(this);
            
            button.prop('disabled', true).text('<?php _e('Generating...', 'snn'); ?>');
            $('#sitemap-results').hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'snn_generate_sitemap',
                    nonce: '<?php echo wp_create_nonce('snn_sitemap_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        const message = response.data.message + 
                            '<br><strong><?php _e('Files Created:', 'snn'); ?></strong> ' + response.data.files_created +
                            '<br><strong><?php _e('Total URLs:', 'snn'); ?></strong> ' + response.data.total_urls.toLocaleString() +
                            '<br><strong><?php _e('Processing Time:', 'snn'); ?></strong> ' + response.data.processing_time + 's';
                        showSitemapResults('success', message);
                    } else {
                        showSitemapResults('error', '<?php _e('Failed to generate sitemap', 'snn'); ?>: ' + response.data.message);
                    }
                },
                error: function() {
                    showSitemapResults('error', '<?php _e('An error occurred while generating sitemap', 'snn'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Generate/Update Sitemap', 'snn'); ?>');
                }
            });
        });
        
        $('#delete-sitemap').on('click', function() {
            if (!confirm('<?php _e('Are you sure you want to delete all sitemap files?', 'snn'); ?>')) {
                return;
            }
            
            const button = $(this);
            button.prop('disabled', true).text('<?php _e('Deleting...', 'snn'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'snn_delete_sitemap',
                    nonce: '<?php echo wp_create_nonce('snn_sitemap_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showSitemapResults('success', '<?php _e('All sitemap files deleted successfully', 'snn'); ?>');
                    } else {
                        showSitemapResults('error', '<?php _e('Failed to delete sitemap files', 'snn'); ?>');
                    }
                },
                error: function() {
                    showSitemapResults('error', '<?php _e('An error occurred while deleting sitemap files', 'snn'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Delete All Sitemaps', 'snn'); ?>');
                }
            });
        });
        
        function showSitemapResults(type, message) {
            const resultClass = type === 'success' ? 'notice-success' : 'notice-error';
            const icon = type === 'success' ? '✅' : '❌';
            
            $('#sitemap-results').html(`
                <div class="notice ${resultClass}">
                    <p>${icon} ${message}</p>
                </div>
            `).show();
        }
    });
    </script>
    <?php
}
