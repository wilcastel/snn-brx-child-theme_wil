<?php
/**
 * XML Sitemaps Admin Page - Simple Version
 * 
 * Admin interface for XML sitemaps configuration
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
    
    $options = get_option('snn_xml_sitemaps_options', array());
    $sitemap_url = home_url('/sitemap.xml');
    
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
                </div>
                
                <div class="snn-sitemap-actions-buttons">
                    <button id="generate-sitemap" class="button button-primary">
                        <?php _e('Generate/Update Sitemap', 'snn'); ?>
                    </button>
                    
                    <button id="view-sitemap" class="button button-secondary" onclick="window.open('<?php echo esc_url($sitemap_url); ?>', '_blank')">
                        <?php _e('View Sitemap', 'snn'); ?>
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
    .snn-sitemap-settings {
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
    
    @media (max-width: 768px) {
        .snn-xml-sitemaps-admin {
            grid-template-columns: 1fr;
        }
        
        .snn-sitemap-actions-buttons {
            flex-direction: column;
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
                            '<br><strong><?php _e('Total URLs:', 'snn'); ?></strong> ' + response.data.total_urls.toLocaleString();
                        showSitemapResults('success', message);
                    } else {
                        showSitemapResults('error', '<?php _e('Failed to generate sitemap', 'snn'); ?>');
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
