<?php
/**
 * Assets Optimization Settings Page
 * 
 * Adds Assets Optimization to the SNN Settings menu
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Assets Optimization submenu
 */
function snn_add_assets_optimization_submenu() {
    // Debug: Check if function exists
    if (!function_exists('snn_render_assets_optimization_page')) {
        error_log('snn_render_assets_optimization_page function not found');
        return;
    }
    
    add_submenu_page(
        'snn-settings',
        __('Assets Optimization', 'snn'),
        __('Assets Optimization', 'snn'),
        'manage_options',
        'snn-assets-optimization',
        'snn_render_assets_optimization_page'
    );
}
add_action('admin_menu', 'snn_add_assets_optimization_submenu', 25);
