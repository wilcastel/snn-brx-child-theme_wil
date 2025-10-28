<?php
/**
 * WebP Image Optimization Admin Page
 * 
 * Admin interface for WebP image optimization configuration
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render WebP Image Optimization admin page
 */
function snn_render_webp_optimization_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    $options = get_option('snn_webp_options', array());
    
    // Get image statistics
    $total_images = wp_count_attachments('image');
    $total_count = ($total_images->inherit ?? 0) + ($total_images->private ?? 0) + ($total_images->trash ?? 0);
    $webp_images = snn_count_webp_images();
    $saved_space = snn_calculate_saved_space();
    
    ?>
    <div class="wrap">
        <h1><?php _e('WebP Image Optimization', 'snn'); ?></h1>
        
        <div class="snn-webp-optimization-admin">
            <div class="snn-webp-stats">
                <h2><?php _e('Image Statistics', 'snn'); ?></h2>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($total_count); ?></span>
                        <span class="stat-label"><?php _e('Total Images', 'snn'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($webp_images); ?></span>
                        <span class="stat-label"><?php _e('WebP Images', 'snn'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $saved_space; ?></span>
                        <span class="stat-label"><?php _e('Space Saved', 'snn'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo round(($webp_images / max($total_count, 1)) * 100, 1); ?>%</span>
                        <span class="stat-label"><?php _e('Conversion Rate', 'snn'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="snn-webp-actions">
                <h2><?php _e('Image Management', 'snn'); ?></h2>
                
                <div class="snn-webp-actions-buttons">
                    <button id="convert-images" class="button button-primary">
                        <?php _e('Convert All Images to WebP', 'snn'); ?>
                    </button>
                    
                    <button id="optimize-images" class="button button-secondary">
                        <?php _e('Optimize Images', 'snn'); ?>
                    </button>
                    
                    <button id="clear-webp" class="button button-secondary" style="color: #d63638;">
                        <?php _e('Clear WebP Cache', 'snn'); ?>
                    </button>
                </div>
                
                <div id="webp-results" class="snn-webp-results" style="display: none;">
                    <!-- Results will be shown here -->
                </div>
            </div>
            
            <div class="snn-webp-settings">
                <h2><?php _e('Settings', 'snn'); ?></h2>
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields('snn_webp_options');
                    do_settings_sections('snn-webp-optimization');
                    submit_button();
                    ?>
                </form>
            </div>
            
            <div class="snn-webp-info-panel">
                <h2><?php _e('About WebP Image Optimization', 'snn'); ?></h2>
                <p><?php _e('WebP image optimization improves Core Web Vitals by reducing image file sizes while maintaining quality.', 'snn'); ?></p>
                
                <h3><?php _e('Features:', 'snn'); ?></h3>
                <ul>
                    <li><?php _e('Automatic WebP conversion', 'snn'); ?></li>
                    <li><?php _e('Image resizing and optimization', 'snn'); ?></li>
                    <li><?php _e('Critical image preloading', 'snn'); ?></li>
                    <li><?php _e('Lazy loading for non-critical images', 'snn'); ?></li>
                    <li><?php _e('Placeholder images', 'snn'); ?></li>
                    <li><?php _e('Fallback support for older browsers', 'snn'); ?></li>
                </ul>
                
                <h3><?php _e('Core Web Vitals Benefits:', 'snn'); ?></h3>
                <ul>
                    <li><?php _e('LCP: Faster loading of critical images', 'snn'); ?></li>
                    <li><?php _e('CLS: Reduced layout shift with placeholders', 'snn'); ?></li>
                    <li><?php _e('FID: Less blocking resources', 'snn'); ?></li>
                </ul>
                
                <h3><?php _e('Browser Support:', 'snn'); ?></h3>
                <ul>
                    <li><?php _e('Chrome: Full support', 'snn'); ?></li>
                    <li><?php _e('Firefox: Full support', 'snn'); ?></li>
                    <li><?php _e('Safari: Full support', 'snn'); ?></li>
                    <li><?php _e('Edge: Full support', 'snn'); ?></li>
                    <li><?php _e('IE: Fallback to original format', 'snn'); ?></li>
                </ul>
                
                <h3><?php _e('File Structure:', 'snn'); ?></h3>
                <ul>
                    <li><strong><?php _e('Original Images:', 'snn'); ?></strong> <code>/wp-content/uploads/</code></li>
                    <li><strong><?php _e('WebP Images:', 'snn'); ?></strong> <code>/wp-content/uploads/webp/</code></li>
                    <li><strong><?php _e('Automatic Fallback:', 'snn'); ?></strong> <?php _e('Original format for unsupported browsers', 'snn'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <style>
    .snn-webp-optimization-admin {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 20px;
    }
    
    .snn-webp-stats,
    .snn-webp-actions,
    .snn-webp-settings,
    .snn-webp-info-panel {
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }
    
    .stat-item {
        text-align: center;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
    
    .stat-number {
        display: block;
        font-size: 28px;
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
    
    .snn-webp-actions-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    
    .snn-webp-results {
        background: #f0f8ff;
        padding: 15px;
        border-radius: 4px;
        border-left: 4px solid #2271b1;
    }
    
    .snn-webp-info-panel ul {
        margin-left: 20px;
    }
    
    .snn-webp-info-panel li {
        margin-bottom: 8px;
    }
    
    @media (max-width: 768px) {
        .snn-webp-optimization-admin {
            grid-template-columns: 1fr;
        }
        
        .snn-webp-actions-buttons {
            flex-direction: column;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#convert-images').on('click', function() {
            const button = $(this);
            
            button.prop('disabled', true).text('<?php _e('Converting...', 'snn'); ?>');
            $('#webp-results').hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'snn_convert_images',
                    nonce: '<?php echo wp_create_nonce('snn_webp_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        const message = response.data.message + 
                            '<br><strong><?php _e('Converted:', 'snn'); ?></strong> ' + response.data.converted +
                            '<br><strong><?php _e('Errors:', 'snn'); ?></strong> ' + response.data.errors +
                            '<br><strong><?php _e('Total:', 'snn'); ?></strong> ' + response.data.total;
                        showWebPResults('success', message);
                    } else {
                        showWebPResults('error', '<?php _e('Failed to convert images', 'snn'); ?>');
                    }
                },
                error: function() {
                    showWebPResults('error', '<?php _e('An error occurred while converting images', 'snn'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Convert All Images to WebP', 'snn'); ?>');
                }
            });
        });
        
        $('#optimize-images').on('click', function() {
            const button = $(this);
            
            button.prop('disabled', true).text('<?php _e('Optimizing...', 'snn'); ?>');
            $('#webp-results').hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'snn_optimize_images',
                    nonce: '<?php echo wp_create_nonce('snn_webp_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        const message = response.data.message + 
                            '<br><strong><?php _e('Optimized:', 'snn'); ?></strong> ' + response.data.optimized +
                            '<br><strong><?php _e('Space Saved:', 'snn'); ?></strong> ' + response.data.saved_space;
                        showWebPResults('success', message);
                    } else {
                        showWebPResults('error', '<?php _e('Failed to optimize images', 'snn'); ?>');
                    }
                },
                error: function() {
                    showWebPResults('error', '<?php _e('An error occurred while optimizing images', 'snn'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Optimize Images', 'snn'); ?>');
                }
            });
        });
        
        $('#clear-webp').on('click', function() {
            if (!confirm('<?php _e('Are you sure you want to clear the WebP cache?', 'snn'); ?>')) {
                return;
            }
            
            const button = $(this);
            button.prop('disabled', true).text('<?php _e('Clearing...', 'snn'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'snn_clear_webp_cache',
                    nonce: '<?php echo wp_create_nonce('snn_webp_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showWebPResults('success', '<?php _e('WebP cache cleared successfully', 'snn'); ?>');
                    } else {
                        showWebPResults('error', '<?php _e('Failed to clear WebP cache', 'snn'); ?>');
                    }
                },
                error: function() {
                    showWebPResults('error', '<?php _e('An error occurred while clearing cache', 'snn'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Clear WebP Cache', 'snn'); ?>');
                }
            });
        });
        
        function showWebPResults(type, message) {
            const resultClass = type === 'success' ? 'notice-success' : 'notice-error';
            const icon = type === 'success' ? '✅' : '❌';
            
            $('#webp-results').html(`
                <div class="notice ${resultClass}">
                    <p>${icon} ${message}</p>
                </div>
            `).show();
        }
    });
    </script>
    <?php
}

/**
 * Count WebP images
 */
function snn_count_webp_images() {
    $upload_dir = wp_upload_dir();
    $webp_dir = $upload_dir['basedir'] . '/webp/';
    
    if (!file_exists($webp_dir)) {
        return 0;
    }
    
    $files = glob($webp_dir . '*.webp');
    return count($files);
}

/**
 * Calculate saved space
 */
function snn_calculate_saved_space() {
    $upload_dir = wp_upload_dir();
    $webp_dir = $upload_dir['basedir'] . '/webp/';
    
    if (!file_exists($webp_dir)) {
        return '0 MB';
    }
    
    $total_size = 0;
    $files = glob($webp_dir . '*.webp');
    
    foreach ($files as $file) {
        $total_size += filesize($file);
    }
    
    return size_format($total_size);
}
