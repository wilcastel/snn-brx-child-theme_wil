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
                    
                    <button id="start-background-conversion" class="button button-primary" style="background: #10b981; border-color: #10b981;">
                        <?php _e('Start Background Conversion', 'snn'); ?>
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
    
    /* Progress Bar Styles */
    .snn-progress-container {
        background: #f0f8ff;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #2271b1;
        margin: 20px 0;
    }
    
    .snn-progress-bar {
        width: 100%;
        height: 20px;
        background: #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 10px;
    }
    
    .snn-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #2271b1, #10b981);
        border-radius: 10px;
        transition: width 0.3s ease-in-out;
        position: relative;
    }
    
    .snn-progress-fill::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        animation: shimmer 2s infinite;
    }
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    
    .snn-progress-text {
        text-align: center;
        font-weight: bold;
        color: #2271b1;
        margin-bottom: 15px;
    }
    
    .snn-progress-actions {
        text-align: center;
    }
    
    .snn-progress-actions .button {
        margin: 0 5px;
    }
    
    .snn-progress-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 4px;
        margin-top: 10px;
        border-left: 3px solid #10b981;
    }
    
    .snn-progress-info p {
        margin: 5px 0;
        font-size: 14px;
    }
    
    .snn-progress-info p:first-child {
        font-weight: bold;
        color: #10b981;
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
        let conversionState = {
            isRunning: false,
            totalProcessed: 0,
            totalConverted: 0,
            totalErrors: 0,
            currentOffset: 0,
            batchSize: <?php echo intval(get_option('snn_webp_options')['batch_size'] ?? 50); ?>
        };
        
        $('#convert-images').on('click', function() {
            if (conversionState.isRunning) {
                stopConversion();
                return;
            }
            
            startConversion();
        });
        
        function startConversion() {
            const button = $('#convert-images');
            
            conversionState.isRunning = true;
            conversionState.totalProcessed = 0;
            conversionState.totalConverted = 0;
            conversionState.totalErrors = 0;
            conversionState.currentOffset = 0;
            
            button.prop('disabled', true).text('<?php _e('Converting...', 'snn'); ?>');
            $('#webp-results').hide();
            
            // Show progress bar
            showProgressBar();
            
            processBatch();
        }
        
        function stopConversion() {
            conversionState.isRunning = false;
            const button = $('#convert-images');
            button.prop('disabled', false).text('<?php _e('Convert All Images to WebP', 'snn'); ?>');
            
            showWebPResults('warning', '<?php _e('Conversion stopped by user', 'snn'); ?>');
        }
        
        function processBatch() {
            if (!conversionState.isRunning) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'snn_convert_images',
                    nonce: '<?php echo wp_create_nonce('snn_webp_nonce'); ?>',
                    batch_size: conversionState.batchSize,
                    offset: conversionState.currentOffset,
                    total_processed: conversionState.totalProcessed,
                    total_converted: conversionState.totalConverted,
                    total_errors: conversionState.totalErrors
                },
                success: function(response) {
                    if (response.success) {
                        // Update state
                        conversionState.totalProcessed = response.data.total_processed;
                        conversionState.totalConverted = response.data.total_converted;
                        conversionState.totalErrors = response.data.total_errors;
                        conversionState.currentOffset = response.data.next_offset;
                        
                        // Update progress
                        updateProgress(response.data.progress_percent);
                        
                        if (response.data.has_more) {
                            // Continue with next batch
                            setTimeout(processBatch, 100); // Small delay to prevent server overload
                        } else {
                            // Conversion completed
                            completeConversion();
                        }
                    } else {
                        showWebPResults('error', '<?php _e('Failed to convert images', 'snn'); ?>');
                        stopConversion();
                    }
                },
                error: function() {
                    showWebPResults('error', '<?php _e('An error occurred while converting images', 'snn'); ?>');
                    stopConversion();
                }
            });
        }
        
        function completeConversion() {
            conversionState.isRunning = false;
            const button = $('#convert-images');
            button.prop('disabled', false).text('<?php _e('Convert All Images to WebP', 'snn'); ?>');
            
            const message = '<?php _e('Conversion completed!', 'snn'); ?>' + 
                '<br><strong><?php _e('Total Processed:', 'snn'); ?></strong> ' + conversionState.totalProcessed.toLocaleString() +
                '<br><strong><?php _e('Converted:', 'snn'); ?></strong> ' + conversionState.totalConverted.toLocaleString() +
                '<br><strong><?php _e('Errors:', 'snn'); ?></strong> ' + conversionState.totalErrors.toLocaleString();
            
            showWebPResults('success', message);
            hideProgressBar();
        }
        
        function showProgressBar() {
            const progressHtml = `
                <div id="conversion-progress" class="snn-progress-container">
                    <div class="snn-progress-bar">
                        <div class="snn-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="snn-progress-text">
                        <span id="progress-percent">0%</span> - 
                        <span id="progress-stats"><?php _e('Starting...', 'snn'); ?></span>
                    </div>
                    <div class="snn-progress-actions">
                        <button id="stop-conversion" class="button button-secondary"><?php _e('Stop Conversion', 'snn'); ?></button>
                    </div>
                </div>
            `;
            
            $('#webp-results').html(progressHtml).show();
            
            $('#stop-conversion').on('click', function() {
                stopConversion();
            });
        }
        
        function updateProgress(percent) {
            $('.snn-progress-fill').css('width', percent + '%');
            $('#progress-percent').text(percent + '%');
            $('#progress-stats').text(
                conversionState.totalProcessed.toLocaleString() + ' <?php _e('processed', 'snn'); ?> | ' +
                conversionState.totalConverted.toLocaleString() + ' <?php _e('converted', 'snn'); ?> | ' +
                conversionState.totalErrors.toLocaleString() + ' <?php _e('errors', 'snn'); ?>'
            );
        }
        
        function hideProgressBar() {
            $('#conversion-progress').remove();
        }
        
        // Background conversion
        $('#start-background-conversion').on('click', function() {
            const button = $(this);
            
            button.prop('disabled', true).text('<?php _e('Starting...', 'snn'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'snn_start_background_conversion',
                    nonce: '<?php echo wp_create_nonce('snn_webp_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showWebPResults('success', '<?php _e('Background conversion started! Check back in a few minutes.', 'snn'); ?>');
                        startStatusPolling();
                    } else {
                        showWebPResults('error', '<?php _e('Failed to start background conversion', 'snn'); ?>');
                    }
                },
                error: function() {
                    showWebPResults('error', '<?php _e('An error occurred while starting background conversion', 'snn'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Start Background Conversion', 'snn'); ?>');
                }
            });
        });
        
        function startStatusPolling() {
            const statusInterval = setInterval(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'snn_get_conversion_status',
                        nonce: '<?php echo wp_create_nonce('snn_webp_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            const status = response.data;
                            
                            if (status.status === 'completed') {
                                clearInterval(statusInterval);
                                showWebPResults('success', 
                                    '<?php _e('Background conversion completed!', 'snn'); ?>' +
                                    '<br><strong><?php _e('Total Processed:', 'snn'); ?></strong> ' + status.total_processed.toLocaleString() +
                                    '<br><strong><?php _e('Converted:', 'snn'); ?></strong> ' + status.total_converted.toLocaleString() +
                                    '<br><strong><?php _e('Errors:', 'snn'); ?></strong> ' + status.total_errors.toLocaleString()
                                );
                            } else if (status.status === 'running') {
                                showBackgroundProgress(status);
                            }
                        }
                    }
                });
            }, 5000); // Poll every 5 seconds
        }
        
        function showBackgroundProgress(status) {
            const progressHtml = `
                <div id="background-progress" class="snn-progress-container">
                    <div class="snn-progress-bar">
                        <div class="snn-progress-fill" style="width: ${status.progress_percent}%"></div>
                    </div>
                    <div class="snn-progress-text">
                        <span id="background-progress-percent">${status.progress_percent}%</span> - 
                        <span id="background-progress-stats">
                            ${status.total_processed.toLocaleString()} <?php _e('processed', 'snn'); ?> | 
                            ${status.total_converted.toLocaleString()} <?php _e('converted', 'snn'); ?> | 
                            ${status.total_errors.toLocaleString()} <?php _e('errors', 'snn'); ?>
                        </span>
                    </div>
                    <div class="snn-progress-info">
                        <p><strong><?php _e('Background Conversion Running', 'snn'); ?></strong></p>
                        <p><?php _e('This process runs in the background and will continue even if you close this page.', 'snn'); ?></p>
                        <p><?php _e('Batch', 'snn'); ?> ${status.current_batch} - <?php _e('Progress updates every 5 seconds', 'snn'); ?></p>
                    </div>
                </div>
            `;
            
            $('#webp-results').html(progressHtml).show();
        }
        
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
