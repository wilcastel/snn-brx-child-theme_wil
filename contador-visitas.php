<?php
// Definir constantes Redis para entorno local si no están definidas
if (!defined('REDIS_HOST')) {
    define('REDIS_HOST', '127.0.0.1');
}
if (!defined('REDIS_PORT')) {
    define('REDIS_PORT', 6379);
}
if (!defined('REDIS_SITE_PREFIX')) {
    define('REDIS_SITE_PREFIX', 'lanacion_visits_');
}

// Conectar a Redis
function redis_connect() {
    static $redis = null;
    if ($redis === null) {
        // Verificar si la clase Redis está disponible
        if (!class_exists('Redis')) {
            error_log("Redis: La clase Redis no está disponible. Usando fallback a base de datos.");
            return false;
        }
        
        $redis = new Redis();
        try {
            // Conexión con timeout de 1 segundo
            $connected = $redis->connect(REDIS_HOST, REDIS_PORT, 1);
            if (!$connected) {
                error_log("Redis: No se pudo conectar a " . REDIS_HOST . ":" . REDIS_PORT);
                return false;
            }
        } catch (Exception $e) {
            error_log("Redis connection error: " . $e->getMessage());
            return false;
        }
    }
    return $redis;
}

/**
 * Incrementar el contador de visitas en Redis o base de datos como fallback
 */
function registrar_visita($post_id) {
    if (is_admin() || !$post_id) return;

    $redis = redis_connect();
    if ($redis) {
        // Usar Redis si está disponible
        $key = REDIS_SITE_PREFIX . $post_id;
        $redis->incr($key);
    } else {
        // Fallback a base de datos de WordPress
        registrar_visita_db($post_id);
    }
}

/**
 * Fallback: Incrementar contador usando base de datos de WordPress
 */
function registrar_visita_db($post_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'post_visits';
    
    // Crear tabla si no existe
    crear_tabla_visitas();
    
    // Incrementar contador
    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$table_name} (post_id, visit_count) VALUES (%d, 1) 
         ON DUPLICATE KEY UPDATE visit_count = visit_count + 1",
        $post_id
    ));
}

/**
 * Crear tabla para almacenar visitas en base de datos
 */
function crear_tabla_visitas() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'post_visits';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        post_id bigint(20) NOT NULL,
        visit_count int(11) NOT NULL DEFAULT 1,
        PRIMARY KEY (post_id)
    ) {$charset_collate};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Obtener el número de visitas de un post desde Redis o base de datos como fallback
 */
function obtener_visitas($post_id) {
    if (!$post_id) return 0;

    $redis = redis_connect();
    if ($redis) {
        // Usar Redis si está disponible
        $key = REDIS_SITE_PREFIX . $post_id;
        $visitas = $redis->get($key);
        return (int)$visitas;
    } else {
        // Fallback a base de datos de WordPress
        return obtener_visitas_db($post_id);
    }
}

/**
 * Fallback: Obtener visitas desde base de datos de WordPress
 */
function obtener_visitas_db($post_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'post_visits';
    
    $visitas = $wpdb->get_var($wpdb->prepare(
        "SELECT visit_count FROM {$table_name} WHERE post_id = %d",
        $post_id
    ));
    
    return (int)$visitas;
}

/**
 * Crear tabla de visitas al activar el tema
 */
add_action('after_switch_theme', 'crear_tabla_visitas');

/**
 * Registrar una visita cuando se visualiza un post individual
 */
add_action('wp', function() {
    if (is_single()) {
        $post = get_queried_object();
        // Verificamos que sea un objeto WP_Post y que esté publicado
        if ($post instanceof WP_Post && $post->post_status === 'publish') {
            registrar_visita($post->ID);
        }
    }
});
