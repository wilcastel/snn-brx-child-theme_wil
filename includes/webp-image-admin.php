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
 * Handle settings save for both option groups
 */
function snn_save_webp_settings() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_POST['snn_save_webp_settings']) && check_admin_referer('snn_webp_settings_nonce', 'snn_webp_settings_nonce')) {
        // Save WebP options - always process even if empty to handle unchecked checkboxes
        $post_options = isset($_POST['snn_webp_options']) ? $_POST['snn_webp_options'] : array();
        
        // Get current options to preserve existing values
        $current_options = get_option('snn_webp_options', array());
        
        // Merge POST data with current options (POST takes precedence)
        $merged_options = array_merge($current_options, $post_options);
        
        // List of all checkbox fields
        $checkbox_fields = array('enable_webp', 'enable_preload', 'enable_lazy_loading', 'enable_placeholder', 'convert_all_sizes', 'auto_serve_webp');
        
        // Set unchecked checkboxes to 0 explicitly (if not in POST, they are unchecked)
        foreach ($checkbox_fields as $field) {
            if (!isset($post_options[$field])) {
                $merged_options[$field] = 0;
            } else {
                // Ensure checked checkboxes are set to 1 (not string "1")
                $merged_options[$field] = 1;
            }
        }
        
        // Create temporary instance to access sanitize method
        $temp_optimizer = new SNN_WebP_Image_Optimizer();
        $sanitized = $temp_optimizer->sanitize_options($merged_options);
        
        // Force update - delete first to ensure clean state
        delete_option('snn_webp_options');
        $result = update_option('snn_webp_options', $sanitized, false);
        
        // Debug: Log what was saved (temporary)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SNN WebP Options Saved: ' . print_r($sanitized, true));
        }
        
        // Save Image Optimizer options - always process even if empty to handle unchecked checkboxes
        $post_image_options = isset($_POST['snn_image_optimizer_options']) ? $_POST['snn_image_optimizer_options'] : array();
        
        // Get current options to preserve existing values
        $current_image_options = get_option('snn_image_optimizer_options', array());
        
        // Merge POST data with current options (POST takes precedence)
        $merged_image_options = array_merge($current_image_options, $post_image_options);
        
        // List of all checkbox fields for Image Optimizer
        $checkbox_fields = array('enable_auto_optimization');
        
        // Set unchecked checkboxes to 0 explicitly (if not in POST, they are unchecked)
        foreach ($checkbox_fields as $field) {
            if (!isset($post_image_options[$field])) {
                $merged_image_options[$field] = 0;
            } else {
                // Ensure checked checkboxes are set to 1 (not string "1")
                $merged_image_options[$field] = 1;
            }
        }
        
        $temp_auto_optimizer = new SNN_Image_Auto_Optimizer();
        $sanitized = $temp_auto_optimizer->sanitize_options($merged_image_options);
        
        // Force update - delete first to ensure clean state
        delete_option('snn_image_optimizer_options');
        $result = update_option('snn_image_optimizer_options', $sanitized, false);
        
        // Debug: Log what was saved (temporary)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SNN Image Optimizer Options Saved: ' . print_r($sanitized, true));
        }
        
        // Clear any object cache that might interfere
        wp_cache_delete('snn_webp_options', 'options');
        wp_cache_delete('snn_image_optimizer_options', 'options');
        wp_cache_flush(); // Flush all cache
        
        // Verify what was actually saved
        $verify_webp = get_option('snn_webp_options', array());
        $verify_image = get_option('snn_image_optimizer_options', array());
        
        // Debug: Log what was read back (temporary)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SNN WebP Options Read Back: ' . print_r($verify_webp, true));
            error_log('SNN Image Optimizer Options Read Back: ' . print_r($verify_image, true));
        }
        
        // Redirect to prevent resubmission
        wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=snn-webp-optimization')));
        exit;
    }
}
add_action('admin_init', 'snn_save_webp_settings');

/**
 * Render WebP Image Optimization admin page
 */
function snn_render_webp_optimization_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Show success message
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'snn') . '</p></div>';
    }
    
    $options = get_option('snn_webp_options', array());
    
    ?>
    <div class="wrap">
        <h1><?php _e('WebP Image Optimization', 'snn'); ?></h1>
        
        <div class="snn-webp-optimization-admin">
            <div class="snn-webp-main-content">
                <div class="snn-webp-actions">
                    <h2><?php _e('Image Management', 'snn'); ?></h2>
                    
                    <div class="snn-webp-actions-buttons">
                        <button id="convert-recent-posts-images" class="button button-primary" style="margin-bottom: 10px;">
                            <?php _e('Convertir Imágenes de los 100 Últimos Posts', 'snn'); ?>
                        </button>
                        <p class="description" style="margin-bottom: 15px;">
                            <?php _e('Convierte las imágenes destacadas y las imágenes del contenido de los 100 posts más recientes a formato WebP.', 'snn'); ?>
                        </p>
                        
                        <input type="text" id="snn-folder-path" class="regular-text" style="min-width:340px" />
                        <button id="convert-folder" class="button button-primary">
                            <?php _e('Convert Folder to WebP', 'snn'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php 
                            $u = wp_upload_dir(); 
                            echo esc_html( sprintf(__("Absolute path or uploads-relative path. Uploads base: %s", 'snn'), $u['basedir']) );
                        ?>
                    </p>
                    
                    <div id="webp-results" class="snn-webp-results" style="display: none;">
                        <!-- Results will be shown here -->
                    </div>
                </div>
                
                <div class="snn-webp-settings">
                    <h2><?php _e('Settings', 'snn'); ?></h2>
                    
                    <form method="post" action="">
                        <?php
                        wp_nonce_field('snn_webp_settings_nonce', 'snn_webp_settings_nonce');
                        ?>
                        <input type="hidden" name="snn_save_webp_settings" value="1" />
                        
                        <?php
                        // Display all settings sections
                        do_settings_sections('snn-webp-optimization');
                        
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .snn-webp-optimization-admin {
        margin-top: 20px;
    }
    
    .snn-webp-main-content {
        max-width: 1200px;
    }
    
    .snn-webp-actions,
    .snn-webp-settings {
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        margin-bottom: 30px;
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
        .snn-webp-actions-buttons {
            flex-direction: column;
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
        
        // Convert specific folder
        $('#convert-folder').on('click', function() {
            const folderInput = $('#snn-folder-path');
            const folder = folderInput.val().trim();
            if (!folder) {
                showWebPResults('error', '<?php _e('Please enter a folder path', 'snn'); ?>');
                return;
            }
            const button = $(this);
            button.prop('disabled', true).text('<?php _e('Converting folder...', 'snn'); ?>');
            
            showProgressBar();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'snn_convert_folder',
                    nonce: '<?php echo wp_create_nonce('snn_webp_nonce'); ?>',
                    folder
                },
                success: function(response) {
                    if (response.success) {
                        const d = response.data;
                        updateProgress(100);
                        const message = '<?php _e('Folder conversion completed!', 'snn'); ?>' +
                          '<br><strong><?php _e('Processed:', 'snn'); ?></strong> ' + d.processed.toLocaleString() +
                          '<br><strong><?php _e('Converted:', 'snn'); ?></strong> ' + d.converted.toLocaleString() +
                          '<br><strong><?php _e('Errors:', 'snn'); ?></strong> ' + d.errors.toLocaleString();
                        showWebPResults('success', message);
                    } else {
                        showWebPResults('error', response.data?.message || '<?php _e('Failed to convert folder', 'snn'); ?>');
                    }
                },
                error: function() {
                    showWebPResults('error', '<?php _e('An error occurred while converting folder', 'snn'); ?>');
                },
                complete: function() {
                    hideProgressBar();
                    button.prop('disabled', false).text('<?php _e('Convert Folder to WebP', 'snn'); ?>');
                }
            });
        });
        
        // Convert images from recent posts
        let recentPostsConversionState = {
            isRunning: false,
            totalProcessed: 0,
            totalConverted: 0,
            totalErrors: 0,
            offset: 0
        };
        
        $('#convert-recent-posts-images').on('click', function() {
            if (recentPostsConversionState.isRunning) {
                stopRecentPostsConversion();
                return;
            }
            
            if (!confirm('<?php _e('¿Convertir imágenes de los 100 últimos posts? Esto puede tardar varios minutos.', 'snn'); ?>')) {
                return;
            }
            
            const button = $('#convert-recent-posts-images');
            button.prop('disabled', true).text('<?php _e('Convirtiendo...', 'snn'); ?>');
            
            recentPostsConversionState.isRunning = true;
            recentPostsConversionState.totalProcessed = 0;
            recentPostsConversionState.totalConverted = 0;
            recentPostsConversionState.totalErrors = 0;
            recentPostsConversionState.offset = 0;
            
            showWebPResults('info', '<?php _e('Iniciando conversión de imágenes de los 100 últimos posts...', 'snn'); ?>');
            processRecentPostsBatch();
        });
        
        function stopRecentPostsConversion() {
            recentPostsConversionState.isRunning = false;
            const button = $('#convert-recent-posts-images');
            button.prop('disabled', false).text('<?php _e('Convertir Imágenes de los 100 Últimos Posts', 'snn'); ?>');
            showWebPResults('warning', '<?php _e('Conversión detenida por el usuario', 'snn'); ?>');
        }
        
        function processRecentPostsBatch() {
            if (!recentPostsConversionState.isRunning) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'snn_convert_recent_posts_images',
                    nonce: '<?php echo wp_create_nonce('snn_webp_nonce'); ?>',
                    posts_per_batch: 10,
                    offset: recentPostsConversionState.offset,
                    total_processed: recentPostsConversionState.totalProcessed,
                    total_converted: recentPostsConversionState.totalConverted,
                    total_errors: recentPostsConversionState.totalErrors
                },
                success: function(response) {
                    if (response.success) {
                        recentPostsConversionState.totalProcessed = response.data.total_processed;
                        recentPostsConversionState.totalConverted = response.data.total_converted;
                        recentPostsConversionState.totalErrors = response.data.total_errors;
                        recentPostsConversionState.offset = response.data.next_offset;
                        
                        const progress = response.data.progress_percent || 0;
                        updateProgress(progress);
                        
                        const message = response.data.message + '<br>' +
                            '<strong><?php _e('Posts procesados:', 'snn'); ?></strong> ' + response.data.total_processed + '<br>' +
                            '<strong><?php _e('Imágenes convertidas:', 'snn'); ?></strong> ' + response.data.total_converted + '<br>' +
                            '<strong><?php _e('Errores:', 'snn'); ?></strong> ' + response.data.total_errors;
                        
                        showWebPResults('info', message);
                        
                        if (response.data.has_more) {
                            setTimeout(processRecentPostsBatch, 500);
                        } else {
                            completeRecentPostsConversion();
                        }
                    } else {
                        showWebPResults('error', response.data.message || '<?php _e('Error en la conversión', 'snn'); ?>');
                        stopRecentPostsConversion();
                    }
                },
                error: function() {
                    showWebPResults('error', '<?php _e('Error de conexión', 'snn'); ?>');
                    stopRecentPostsConversion();
                }
            });
        }
        
        function completeRecentPostsConversion() {
            recentPostsConversionState.isRunning = false;
            const button = $('#convert-recent-posts-images');
            button.prop('disabled', false).text('<?php _e('Convertir Imágenes de los 100 Últimos Posts', 'snn'); ?>');
            
            updateProgress(100);
            
            // Invalidar cache y recargar estadísticas
            $(document).trigger('webp-conversion-complete');
            
            const message = '<?php _e('Conversión completada!', 'snn'); ?>' +
                '<br><strong><?php _e('Posts procesados:', 'snn'); ?></strong> ' + recentPostsConversionState.totalProcessed +
                '<br><strong><?php _e('Imágenes convertidas:', 'snn'); ?></strong> ' + recentPostsConversionState.totalConverted +
                '<br><strong><?php _e('Errores:', 'snn'); ?></strong> ' + recentPostsConversionState.totalErrors;
            
            showWebPResults('success', message);
        }
        
        
        $('#test-conversion').on('click', function() {
            const button = $(this);
            button.prop('disabled', true).text('<?php _e('Testing...', 'snn'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'snn_test_conversion',
                    nonce: '<?php echo wp_create_nonce('snn_webp_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showWebPResults('success', response.data.message);
                    } else {
                        showWebPResults('error', response.data.message || '<?php _e('Test failed', 'snn'); ?>');
                    }
                },
                error: function() {
                    showWebPResults('error', '<?php _e('An error occurred during test', 'snn'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Test Single Conversion', 'snn'); ?>');
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
 * Get accurate image count
 */
function snn_get_accurate_image_count() {
    global $wpdb;
    
    // Get count of all image attachments
    $count = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} 
        WHERE post_type = 'attachment' 
        AND post_mime_type LIKE 'image/%'
        AND post_status = 'inherit'
    ");
    
    // Debug: Log removido - Solo se usa cuando es necesario para debugging
    
    return intval($count);
}

/**
 * Count WebP images (All locations)
 */
function snn_count_webp_images() {
    $upload_dir = wp_upload_dir();
    $upload_basedir = $upload_dir['basedir'];
    
    // Count WebP files in uploads directory recursively (Windows compatible)
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($upload_basedir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'webp') {
            $count++;
        }
    }
    
    // Debug: Log removido - Solo se usa cuando es necesario para debugging
    
    return $count;
}

/**
 * Calculate saved space (All WebP files)
 */
function snn_calculate_saved_space() {
    $upload_dir = wp_upload_dir();
    $upload_basedir = $upload_dir['basedir'];
    
    $total_size = 0;
    
    // Count WebP files and calculate total size (Windows compatible)
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($upload_basedir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'webp') {
            $total_size += $file->getSize();
        }
    }
    
    return size_format($total_size);
}

/**
 * Count images on disk by extension and compute conversion rate
 */
function snn_count_images_on_disk() {
    $upload_dir = wp_upload_dir();
    $base = $upload_dir['basedir'];
    $counts = array('jpg'=>0,'jpeg'=>0,'png'=>0,'webp'=>0);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) { continue; }
        $ext = strtolower($file->getExtension());
        if (isset($counts[$ext])) { $counts[$ext]++; }
    }
    $originals = $counts['jpg'] + $counts['jpeg'] + $counts['png'];
    $total = $originals + $counts['webp'];
    $rate = $total > 0 ? ($counts['webp'] / $total) * 100 : 0;
    return array(
        'jpg' => $counts['jpg'],
        'jpeg' => $counts['jpeg'],
        'png' => $counts['png'],
        'webp' => $counts['webp'],
        'total' => $total,
        'originals' => $originals,
        'rate' => round($rate, 1),
    );
}

/**
 * Count pending images (JPG/PNG that don't have WebP versions)
 */
function snn_count_pending_images() {
    $upload_dir = wp_upload_dir();
    $base = $upload_dir['basedir'];
    $pending = 0;
    $total_size = 0;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if (!$file->isFile()) { continue; }
        
        $ext = strtolower($file->getExtension());
        
        // Only check JPG and PNG files
        if (!in_array($ext, array('jpg', 'jpeg', 'png'))) {
            continue;
        }
        
        // Check if WebP version exists in the same directory
        $file_info = pathinfo($file->getPathname());
        $webp_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
        
        if (!file_exists($webp_path)) {
            $pending++;
            $total_size += $file->getSize();
        }
    }
    
    return array(
        'count' => $pending,
        'total_size' => $total_size,
        'total_size_formatted' => size_format($total_size)
    );
}
