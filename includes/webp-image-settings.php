<?php
/**
 * WebP Image Optimization Settings Page
 * 
 * Adds WebP Image Optimization to the SNN Settings menu
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add WebP Image Optimization submenu
 */
function snn_add_webp_optimization_submenu() {
    add_submenu_page(
        'snn-settings',
        __('WebP Image Optimization', 'snn'),
        __('WebP Images', 'snn'),
        'manage_options',
        'snn-webp-optimization',
        'snn_render_webp_optimization_page'
    );
}
add_action('admin_menu', 'snn_add_webp_optimization_submenu', 25);
