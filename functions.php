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

// Legacy security files (kept for compatibility - functionality moved to security-optimization.php)
// require_once SNN_PATH . 'includes/security-page.php';
// require_once SNN_PATH . 'includes/remove-wp-version.php';
// require_once SNN_PATH . 'includes/disable-xmlrpc.php';
// require_once SNN_PATH . 'includes/disable-file-editing.php';
// require_once SNN_PATH . 'includes/remove-rss.php';
// require_once SNN_PATH . 'includes/disable-wp-json-if-not-logged-in.php';

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


/****************** 
 * Bricks Builder - Cached WP Query System
 * Cachea un WP_Query completo para reutilizarlo en múltiples loops
 * con diferentes offsets y límites sin consultas adicionales a MySQL
 ******************/

/* Add new query type control to query options */
add_filter( 'bricks/setup/control_options', 'bl_setup_query_controls');
function bl_setup_query_controls( $control_options ) {
    /* Adding a new option in the dropdown */
    $control_options['queryTypes']['cached_wp_query'] = esc_html__( 'Cached WP Query' );
    return $control_options;
}

/* Cache estático para almacenar los WP_Query completos */
function bl_get_cached_queries() {
    static $cached_queries = [];
    return $cached_queries;
}

function bl_set_cached_query( $cache_id, $wp_query ) {
    static $cached_queries = [];
    $cached_queries[$cache_id] = $wp_query;
    return $cached_queries;
}

/* Run new query if option selected - Prioridad alta para ejecutarse antes que otros filtros */
add_filter( 'bricks/query/run', 'bl_maybe_run_cached_query', 5, 2);
function bl_maybe_run_cached_query( $results, $query_obj ) {
    // Solo procesar si es nuestro tipo de query
    if ( $query_obj->object_type !== 'cached_wp_query' ) {
        return $results;
    }
    
    // Intentar obtener settings de diferentes lugares donde Bricks puede guardarlos
    $settings = [];
    
    // Primero intentar desde query_obj->settings
    if ( isset( $query_obj->settings ) && is_array( $query_obj->settings ) ) {
        $settings = $query_obj->settings;
    }
    
    // Si no hay settings, intentar desde el elemento
    if ( empty( $settings ) && isset( $query_obj->element ) ) {
        if ( isset( $query_obj->element->settings ) && is_array( $query_obj->element->settings ) ) {
            $settings = $query_obj->element->settings;
        }
    }
    
    // Si aún no hay settings, intentar desde query_obj directamente
    if ( empty( $settings ) ) {
        $settings = (array) $query_obj;
    }
    
    // Verificar que existe el cache_id
    if ( !isset( $settings['cached_wp_query_cache_id'] ) || empty( $settings['cached_wp_query_cache_id'] ) ) {
        return $results;
    }
    
    $cache_id = sanitize_text_field( $settings['cached_wp_query_cache_id'] );
    
    // PRIMERO: Leer los parámetros del loop (offset y posts_per_page) ANTES de procesar la query base
    // Esto es importante porque estos valores pueden estar en cached_wp_query_args pero NO deben usarse para la query base
    
    // Leer offset - puede venir desde el campo directo o desde cached_wp_query_args
    $offset = 0;
    
    // Primero intentar desde el campo directo
    if ( isset( $settings['cached_wp_query_offset'] ) ) {
        $offset_value = $settings['cached_wp_query_offset'];
        if ( $offset_value !== '' && $offset_value !== null && $offset_value !== false ) {
            $offset = intval( $offset_value );
        }
    }
    
    // Si no está en el campo directo, intentar desde cached_wp_query_args (pero solo para el loop, no para la query base)
    if ( $offset === 0 && isset( $settings['cached_wp_query_args']['offset'] ) ) {
        $offset_value = $settings['cached_wp_query_args']['offset'];
        if ( $offset_value !== '' && $offset_value !== null && $offset_value !== false ) {
            $offset = intval( $offset_value );
        }
    }
    
    // Leer posts_per_page - puede venir desde el campo directo o desde cached_wp_query_args
    $posts_per_page = null;
    
    // Primero intentar desde el campo directo
    if ( isset( $settings['cached_wp_query_posts_per_page'] ) ) {
        $ppp_value = $settings['cached_wp_query_posts_per_page'];
        if ( $ppp_value !== '' && $ppp_value !== null && $ppp_value !== false ) {
            $posts_per_page = intval( $ppp_value );
        }
    }
    
    // Si no está en el campo directo, intentar desde cached_wp_query_args (pero solo para el loop, no para la query base)
    if ( $posts_per_page === null && isset( $settings['cached_wp_query_args']['posts_per_page'] ) ) {
        $ppp_value = $settings['cached_wp_query_args']['posts_per_page'];
        if ( $ppp_value !== '' && $ppp_value !== null && $ppp_value !== false ) {
            $posts_per_page = intval( $ppp_value );
        }
    }
    
    // Obtener los argumentos de la consulta base (SIN offset ni posts_per_page)
    $query_args = [];
    if ( isset( $settings['cached_wp_query_args'] ) && !empty( $settings['cached_wp_query_args'] ) ) {
        // Hacer una copia de los argumentos para no modificar el original
        $query_args = is_array( $settings['cached_wp_query_args'] ) 
            ? $settings['cached_wp_query_args'] 
            : [];
        
        // IMPORTANTE: Remover posts_per_page y offset de la query base
        // porque queremos traer TODOS los posts y luego aplicar filtros por loop
        // Estos valores ya los leímos arriba para usar en el loop
        unset( $query_args['posts_per_page'] );
        unset( $query_args['offset'] );
        unset( $query_args['paged'] );
        
        // Asegurar que traemos TODOS los posts (sin límite)
        $query_args['posts_per_page'] = -1; // -1 = todos los posts disponibles
        $query_args['no_found_rows'] = false; // Necesario para que funcione correctamente
    } else {
        // Valores por defecto si no se especifica
        $query_args = [
            'post_type' => 'post',
            'posts_per_page' => -1, // Traer todos los posts
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => false,
        ];
    }
    
    // Obtener el caché estático (global para toda la petición)
    static $cached_queries = [];
    static $cached_posts = []; // Cachear también los posts directamente para acceso más rápido
    
    // Si no existe el WP_Query en caché, crearlo y guardarlo
    if ( !isset( $cached_queries[$cache_id] ) ) {
        // Crear el WP_Query completo con todos los posts (sin límite)
        $wp_query = new WP_Query( $query_args );
        
        // Guardar el WP_Query completo en caché
        $cached_queries[$cache_id] = $wp_query;
        
        // Guardar también los posts directamente para acceso más rápido
        $cached_posts[$cache_id] = $wp_query->posts;
    }
    
    // Obtener todos los posts del caché (usar el array directo en lugar del WP_Query)
    $all_posts = $cached_posts[$cache_id];
    
    // Si no hay posts, devolver array vacío
    if ( empty( $all_posts ) ) {
        return [];
    }
    
    // Aplicar offset primero
    if ( $offset > 0 ) {
        if ( count( $all_posts ) > $offset ) {
            $all_posts = array_slice( $all_posts, $offset );
        } else {
            // Si el offset es mayor que los posts disponibles, devolver array vacío
            return [];
        }
    }
    
    // Aplicar posts_per_page si se especifica
    if ( $posts_per_page !== null && $posts_per_page > 0 ) {
        $all_posts = array_slice( $all_posts, 0, $posts_per_page );
    }
    
    // Devolver nuestros resultados filtrados, ignorando cualquier resultado que Bricks haya generado
    // Esto asegura que cada loop use el caché compartido con sus propios filtros
    return $all_posts;
}

/* Setup post data for posts */
add_filter( 'bricks/query/loop_object', 'bl_setup_cached_post_data', 10, 3);
function bl_setup_cached_post_data( $loop_object, $loop_key, $query_obj ) {
    if ( $query_obj->object_type !== 'cached_wp_query' ) {
        return $loop_object;
    }
    
    global $post;
    
    // Si $loop_object es un post object, usarlo directamente
    if ( is_object( $loop_object ) && isset( $loop_object->ID ) ) {
        $post = $loop_object;
    } else {
        // Si es solo un ID, obtener el post
        $post = get_post( $loop_object );
    }
    
    if ( $post ) {
        setup_postdata( $post );
    }
    
    return $loop_object;
}

/****************** 
 * Extra Query Controls
 * Agrega controles en la UI de Bricks para configurar la consulta cacheada
 * @see reference https://itchycode.com/integrate-jetengine-query-builder-in-bricks-query-loop-non-official/
 ******************/
add_action( 'init', 'bl_add_cached_query_controls_to_elements', 40 );
function bl_add_cached_query_controls_to_elements() {
    // Only container, block and div element have query controls
    $elements = [ 'container', 'block', 'div' ];
    foreach ( $elements as $name ) {
        add_filter( "bricks/elements/{$name}/controls", 'bl_add_cached_query_controls', 40 );
    }
}

function bl_add_cached_query_controls( $controls ) {
    $my_controls = [
        'cached_wp_query_cache_id' => [
            'tab' => 'content',
            'label' => esc_html__( 'Cache ID', 'bricks' ),
            'type' => 'text',
            'required' => array( 
                [ 'query.objectType', '=', 'cached_wp_query' ], 
                [ 'hasLoop', '!=', false ] 
            ),
            'rerender' => true,
            'description' => esc_html__( 'ID único para el caché. Múltiples loops pueden usar el mismo ID con diferentes offsets/límites.', 'bricks' )
        ],
        'cached_wp_query_args' => [
            'tab' => 'content',
            'label' => esc_html__( 'Query Arguments (Base Query)', 'bricks' ),
            'type' => 'query',
            'default' => [
                'post_type' => 'post',
                'orderby' => 'date',
                'order' => 'DESC',
            ],
            'required' => array( 
                [ 'query.objectType', '=', 'cached_wp_query' ], 
                [ 'hasLoop', '!=', false ] 
            ),
            'rerender' => true,
            'description' => esc_html__( 'Argumentos de WP_Query para la consulta grande. NO incluyas posts_per_page aquí - se traerán todos los posts. Usa Offset y Posts per Page abajo para cada loop.', 'bricks' )
        ],
        'cached_wp_query_offset' => [
            'tab' => 'content',
            'label' => esc_html__( 'Offset', 'bricks' ),
            'type' => 'number',
            'required' => array( 
                [ 'query.objectType', '=', 'cached_wp_query' ], 
                [ 'hasLoop', '!=', false ] 
            ),
            'rerender' => true,
            'description' => esc_html__( 'Número de posts a saltar desde el inicio. Ej: 0 para el primero, 1 para empezar desde el segundo. Déjalo en 0 o vacío para empezar desde el principio.', 'bricks' ),
            'min' => 0,
            'default' => 0,
            'placeholder' => '0',
        ],
        'cached_wp_query_posts_per_page' => [
            'tab' => 'content',
            'label' => esc_html__( 'Posts per Page', 'bricks' ),
            'type' => 'number',
            'required' => array( 
                [ 'query.objectType', '=', 'cached_wp_query' ], 
                [ 'hasLoop', '!=', false ] 
            ),
            'rerender' => true,
            'description' => esc_html__( 'Número de posts a mostrar en este loop. Déjalo vacío para mostrar todos los disponibles después del offset.', 'bricks' ),
            'min' => 1,
            'placeholder' => 'Todos',
        ],
    ];
    
    // Insertar los controles después del control 'query'
    $query_key_index = absint( array_search( 'query', array_keys( $controls ) ) );
    
    if ( $query_key_index !== false ) {
        $new_controls = array_slice( $controls, 0, $query_key_index + 1, true ) 
            + $my_controls 
            + array_slice( $controls, $query_key_index + 1, null, true );
    } else {
        // Si no se encuentra 'query', agregar al final
        $new_controls = $controls + $my_controls;
    }
    
    return $new_controls;
}
