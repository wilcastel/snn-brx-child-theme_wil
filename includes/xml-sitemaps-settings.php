<?php
/**
 * XML Sitemaps Settings Page
 * 
 * Adds XML Sitemaps to the SNN Settings menu
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add XML Sitemaps submenu
 */
function snn_add_xml_sitemaps_submenu() {
    // Debug: Check if function exists
    if (!function_exists('snn_render_xml_sitemaps_page')) {
        error_log('snn_render_xml_sitemaps_page function not found');
        return;
    }
    
    add_submenu_page(
        'snn-settings',
        __('XML Sitemaps', 'snn'),
        __('XML Sitemaps', 'snn'),
        'manage_options',
        'snn-xml-sitemaps',
        'snn_render_xml_sitemaps_page'
    );
}
add_action('admin_menu', 'snn_add_xml_sitemaps_submenu', 20);
