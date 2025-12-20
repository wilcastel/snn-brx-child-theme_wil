<?php
/**
 * Migration Helper for Security Options
 * 
 * This file helps migrate existing security options to the new unified system.
 * Run this once to migrate existing settings.
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migrate existing security options to new unified system
 */
function snn_migrate_security_options() {
    // Check if migration has already been done
    if (get_option('snn_security_migration_completed')) {
        return;
    }
    
    $new_options = array();
    
    // Migrate from old security options
    $old_options = get_option('snn_security_options', array());
    
    if (!empty($old_options)) {
        // Map old option names to new ones
        $mapping = array(
            'disable_xmlrpc' => 'disable_xmlrpc',
            'disable_json' => 'disable_json_api_guests',
            'disable_file_edit' => 'disable_file_editing',
            'remove_rss' => 'remove_rss_feeds',
            'remove_wp_version' => 'hide_wp_version',
            'disable_bundled_theme_install' => 'disable_bundled_themes',
            'enable_math_captcha' => 'enable_math_captcha',
            'disable_wp_emojicons' => 'disable_emojis',
            'disable_gravatar' => 'disable_gravatar'
        );
        
        foreach ($mapping as $old_key => $new_key) {
            if (isset($old_options[$old_key]) && $old_options[$old_key]) {
                $new_options[$new_key] = 1;
            }
        }
    }
    
    // Save new options
    if (!empty($new_options)) {
        update_option('snn_security_optimization_options', $new_options);
    }
    
    // Mark migration as completed
    update_option('snn_security_migration_completed', true);
    
    // Log migration
    error_log('SNN Security Options Migration Completed: ' . count($new_options) . ' options migrated');
}

/**
 * Add migration notice in admin
 */
function snn_security_migration_notice() {
    if (!get_option('snn_security_migration_completed')) {
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php _e('SNN Security Migration Available', 'snn'); ?></strong><br>
                <?php _e('Your existing security settings can be migrated to the new unified system. ', 'snn'); ?>
                <a href="<?php echo admin_url('admin.php?page=snn-security-optimization&migrate=1'); ?>" class="button button-primary">
                    <?php _e('Migrate Now', 'snn'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'snn_security_migration_notice');

/**
 * Handle migration request
 */
function snn_handle_migration_request() {
    if (isset($_GET['page']) && $_GET['page'] === 'snn-security-optimization' && isset($_GET['migrate']) && $_GET['migrate'] === '1') {
        if (current_user_can('manage_options')) {
            snn_migrate_security_options();
            
            // Redirect to avoid resubmission
            wp_redirect(admin_url('admin.php?page=snn-security-optimization&migrated=1'));
            exit;
        }
    }
}
add_action('admin_init', 'snn_handle_migration_request');

/**
 * Show migration success notice
 */
function snn_migration_success_notice() {
    if (isset($_GET['migrated']) && $_GET['migrated'] === '1') {
        ?>
        <div class="notice notice-success">
            <p>
                <strong><?php _e('Migration Completed Successfully!', 'snn'); ?></strong><br>
                <?php _e('Your security settings have been migrated to the new unified system.', 'snn'); ?>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'snn_migration_success_notice');

/**
 * Clean up old options after successful migration
 */
function snn_cleanup_old_security_options() {
    if (get_option('snn_security_migration_completed')) {
        // Optionally remove old options after migration
        // delete_option('snn_security_options');
        
        // Remove old individual security option groups
        // delete_option('snn_security_options_group');
        // delete_option('snn_security_settings_group');
    }
}

/**
 * Add migration status to admin bar
 */
function snn_add_migration_status_to_admin_bar($wp_admin_bar) {
    if (current_user_can('manage_options') && !get_option('snn_security_migration_completed')) {
        $wp_admin_bar->add_node(array(
            'id' => 'snn-security-migration',
            'title' => __('Security Migration Available', 'snn'),
            'href' => admin_url('admin.php?page=snn-security-optimization&migrate=1'),
            'meta' => array(
                'class' => 'snn-migration-notice'
            )
        ));
    }
}
add_action('admin_bar_menu', 'snn_add_migration_status_to_admin_bar', 100);

/**
 * Add CSS for migration notice
 */
function snn_migration_admin_styles() {
    ?>
    <style>
    .snn-migration-notice {
        background-color: #d63638 !important;
        color: white !important;
    }
    .snn-migration-notice:hover {
        background-color: #b32d2e !important;
    }
    </style>
    <?php
}
add_action('admin_head', 'snn_migration_admin_styles');
