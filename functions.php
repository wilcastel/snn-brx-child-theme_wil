<?php                        
// DO NOT TOUCH THIS FILE 


define( 'SNN_PATH', trailingslashit( get_stylesheet_directory() ) );    
define( 'SNN_PATH_ASSETS', trailingslashit( SNN_PATH . 'assets' ) );    
define( 'SNN_URL', trailingslashit( get_stylesheet_directory_uri() ) ); 
define( 'SNN_URL_ASSETS', trailingslashit( SNN_URL . 'assets' ) );

// Disable XML-RPC early if option is set (before any other code loads)
// This must run before WordPress processes XML-RPC requests
add_action('init', function() {
    $snn_security_options = get_option('snn_security_optimization_options', array());
    if (isset($snn_security_options['disable_xmlrpc']) && $snn_security_options['disable_xmlrpc']) {
        add_filter('xmlrpc_enabled', '__return_false', 1);
    }
}, 1);

// Also block XML-RPC requests directly if option is set
if (!is_admin() && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/xmlrpc.php') !== false) {
    $snn_security_options = get_option('snn_security_optimization_options', array());
    if (isset($snn_security_options['disable_xmlrpc']) && $snn_security_options['disable_xmlrpc']) {
        status_header(403);
        header('Content-Type: text/xml; charset=UTF-8');
        die('<?xml version="1.0" encoding="UTF-8"?><methodResponse><fault><value><struct><member><name>faultCode</name><value><int>403</int></value></member><member><name>faultString</name><value><string>XML-RPC is disabled</string></value></member></struct></value></fault></methodResponse>');
    }
}  


// Main Features and Settings
require_once SNN_PATH . 'includes/settings-page.php';

require_once SNN_PATH . 'includes/other-settings.php';

// Unified Security & Optimization Settings (replaces individual security files)
require_once SNN_PATH . 'includes/security-optimization.php';

// Head optimization (always active for better Core Web Vitals)
require_once SNN_PATH . 'includes/head-optimization.php';

// SEO Optimization (Dynamic Meta Tags)
require_once SNN_PATH . 'includes/seo-optimization.php';


// Security migration helper (run once to migrate existing options)
require_once SNN_PATH . 'includes/security-migration.php';

// XML Sitemaps Settings Page (must be loaded before admin page)
require_once SNN_PATH . 'includes/xml-sitemaps-settings.php';

// XML Sitemaps Admin Page
require_once SNN_PATH . 'includes/xml-sitemaps-admin.php';

// XML Sitemaps Generator (optimized for large sites)
require_once SNN_PATH . 'includes/xml-sitemaps-generator.php';

// XML Sitemaps Rewrite Rules
require_once SNN_PATH . 'includes/xml-sitemaps-rewrite.php';

// Image Auto Optimizer (resize and optimize images on upload)
require_once SNN_PATH . 'includes/image-auto-optimizer.php';

// WebP Image Optimization System
require_once SNN_PATH . 'includes/webp-image-optimizer.php';

// WebP Image Optimization Admin Page
require_once SNN_PATH . 'includes/webp-image-admin.php';

// WebP Image Optimization Settings Page
require_once SNN_PATH . 'includes/webp-image-settings.php';

// Assets Optimization System
require_once SNN_PATH . 'includes/assets-optimization.php';

// Assets Optimization Admin Page
require_once SNN_PATH . 'includes/assets-optimization-admin.php';

// Assets Optimization Settings Page
require_once SNN_PATH . 'includes/assets-optimization-settings.php';


require_once SNN_PATH . 'includes/post-types-settings.php';
require_once SNN_PATH . 'includes/custom-field-settings.php';
require_once SNN_PATH . 'includes/taxonomy-settings.php';
require_once SNN_PATH . 'includes/login-settings.php';
require_once SNN_PATH . 'includes/login-logo-change-url-change.php';
require_once SNN_PATH . 'includes/enqueue-scripts.php';
require_once SNN_PATH . 'includes/file-size-column-media.php';
require_once SNN_PATH . 'includes/404-logging.php';
require_once SNN_PATH . 'includes/search-loggins.php';
require_once SNN_PATH . 'includes/301-redirect.php';
require_once SNN_PATH . 'includes/smtp-settings.php';
require_once SNN_PATH . 'includes/mail-logging.php';
require_once SNN_PATH . 'includes/media-settings.php';
// require_once SNN_PATH . 'includes/disable-emojis.php';  // Moved to security-optimization.php
// require_once SNN_PATH . 'includes/disable-gravatar.php'; // Moved to security-optimization.php
require_once SNN_PATH . 'includes/editor-settings-bricks.php'; 
require_once SNN_PATH . 'includes/editor-settings-panel-bricks.php';
require_once SNN_PATH . 'includes/role-manager.php';
require_once SNN_PATH . 'includes/custom-code-snippets.php';
require_once SNN_PATH . 'includes/cookie-banner.php';
require_once SNN_PATH . 'includes/accessibility-settings.php';
require_once SNN_PATH . 'includes/activity-logs.php';

// require_once SNN_PATH . 'includes/ai.php';
require_once SNN_PATH . 'includes/ai/ai-settings.php';
require_once SNN_PATH . 'includes/ai/ai-api.php';
require_once SNN_PATH . 'includes/ai/ai-overlay.php';
require_once SNN_PATH . 'includes/ai/ai-design.php';

require_once SNN_PATH . 'includes/block-editor-settings.php';
require_once SNN_PATH . 'includes/wp-admin-image-opt.php';


// Register Custom Dynamic Data Tags
require_once SNN_PATH . 'includes/dynamic-data-tags/estimated-post-read-time.php';
require_once SNN_PATH . 'includes/dynamic-data-tags/get-contextual-id.php';
require_once SNN_PATH . 'includes/dynamic-data-tags/parent-link.php';
require_once SNN_PATH . 'includes/dynamic-data-tags/post-term-count.php';
require_once SNN_PATH . 'includes/dynamic-data-tags/user-author-fields.php';
require_once SNN_PATH . 'includes/dynamic-data-tags/custom-field-repeater-first-item.php';

// Utils
require_once SNN_PATH . 'includes/utils.php';
require_once SNN_PATH . 'includes/auto-update-snn-brx-wil-github.php';
require_once SNN_PATH . 'includes/query/snn-repeaters-and-queries.php';

// Redis Cache Helper (debe cargarse antes de cached-wp-query.php)
require_once SNN_PATH . 'includes/redis-cache-helper.php';

// Cache Purge Helper (limpieza de cache en múltiples sistemas)
require_once SNN_PATH . 'includes/cache-purge-helper.php';

// Cache Admin Page (página de administración para gestionar cache)
require_once SNN_PATH . 'includes/cache-admin-page.php';

// Bricks Builder - Cached WP Query System
require_once SNN_PATH . 'includes/cached-wp-query.php';

// contador de visitas
require_once SNN_PATH . 'contador-visitas.php';

// Register Custom Bricks Builder Elements
add_action('init', function () {
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/custom-html-css-script.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/custom-maps.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/advanced-image.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/smoke-text.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/read-more-toggle-text.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/animated-vfx-text.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/polkadot-effect.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/animated-heading.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/svg-text-path.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/timeline.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/like-button.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/flip-box.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/compare-image.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/conditions.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/comment-form.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/comment-list.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/frontend-post-form.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/text-action-social-share.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/scroll-line-vertical-indicator.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/element-event-action-selector.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/matrix.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/multi-step-form.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/query.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/print.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/image-hotspot.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/video-player.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/audio-player.php');
\Bricks\Elements::register_element(SNN_PATH . 'includes/elements/marquee-slider-carousel.php');


// if GSAP setting is enabled Register Elements
$options = get_option('snn_other_settings');

    if (!empty($options['enqueue_gsap'])) {
        \Bricks\Elements::register_element(SNN_PATH . 'includes/elements/lottie-animation.php');
        \Bricks\Elements::register_element(SNN_PATH . 'includes/elements/gsap-animations.php');
        \Bricks\Elements::register_element(SNN_PATH . 'includes/elements/gsap-animations-code.php');
        \Bricks\Elements::register_element(SNN_PATH . 'includes/elements/gsap-text-animations.php');
        \Bricks\Elements::register_element(SNN_PATH . 'includes/elements/svg-animation.php');
        
    }

}, 11);


$options = get_option('snn_other_settings');
if (!empty($options['enqueue_gsap'])) {

    require_once SNN_PATH . 'includes/elements/gsap-multi-element-register.php';

}



// Load Translations
load_theme_textdomain('snn', SNN_PATH . '/languages');
