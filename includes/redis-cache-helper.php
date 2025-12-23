<?php
/**
 * Redis Cache Helper
 * ==================
 * 
 * Sistema de ayuda para usar Redis como Object Cache en WordPress
 * Compatible con el sistema de contador de visitas existente
 * 
 * @package SNN Theme
 * @since 1.0.0
 */

// Definir constantes Redis si no están definidas (compatibilidad con contador-visitas.php)
if (!defined('REDIS_HOST')) {
    define('REDIS_HOST', '127.0.0.1');
}
if (!defined('REDIS_PORT')) {
    define('REDIS_PORT', 6379);
}
if (!defined('REDIS_SITE_PREFIX')) {
    // Usar el prefijo del sitio para evitar conflictos entre sitios
    $site_prefix = defined('DB_NAME') ? DB_NAME : 'lanacion';
    define('REDIS_SITE_PREFIX', $site_prefix . '_');
}

// Prefijo específico para object cache
if (!defined('REDIS_OBJECT_CACHE_PREFIX')) {
    define('REDIS_OBJECT_CACHE_PREFIX', REDIS_SITE_PREFIX . 'cache_');
}

// Prefijo específico para queries cacheadas
if (!defined('REDIS_QUERIES_CACHE_PREFIX')) {
    define('REDIS_QUERIES_CACHE_PREFIX', REDIS_SITE_PREFIX . 'queries_');
}

/**
 * Conectar a Redis (reutilizable)
 * 
 * @return Redis|false Instancia de Redis o false si falla
 */
function snn_redis_connect() {
    static $redis = null;
    
    if ($redis === null) {
        // Verificar si la clase Redis está disponible
        if (!class_exists('Redis')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("SNN Redis: La clase Redis no está disponible. Instala php-redis.");
            }
            return false;
        }
        
        $redis = new Redis();
        try {
            // Conexión con timeout de 1 segundo
            $connected = $redis->connect(REDIS_HOST, REDIS_PORT, 1);
            if (!$connected) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("SNN Redis: No se pudo conectar a " . REDIS_HOST . ":" . REDIS_PORT);
                }
                return false;
            }
            
            // Configurar opciones de Redis
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            
            // Configurar compresión solo si está disponible
            // LZ4 puede no estar disponible en todas las versiones de php-redis
            if (defined('Redis::COMPRESSION_LZ4')) {
                $redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZ4);
            } elseif (defined('Redis::COMPRESSION_ZSTD')) {
                // Fallback a ZSTD si LZ4 no está disponible
                $redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_ZSTD);
            } elseif (defined('Redis::COMPRESSION_LZF')) {
                // Fallback a LZF si las anteriores no están disponibles
                $redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZF);
            }
            // Si ninguna compresión está disponible, simplemente no la usamos
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("SNN Redis connection error: " . $e->getMessage());
            }
            return false;
        }
    }
    
    return $redis;
}

/**
 * Verificar si Redis está disponible
 * 
 * @return bool True si Redis está disponible
 */
function snn_redis_is_available() {
    $redis = snn_redis_connect();
    if (!$redis) {
        return false;
    }
    
    try {
        $ping_result = $redis->ping();
        // Redis puede devolver '+PONG' (string) o true (bool) dependiendo de la versión
        return ($ping_result === '+PONG' || $ping_result === true || $ping_result === 'PONG');
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SNN Redis ping error: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Obtener información detallada de diagnóstico de Redis
 * 
 * @return array Información de diagnóstico
 */
function snn_redis_get_diagnostic() {
    $diagnostic = [];
    
    // Verificar clase Redis
    if (class_exists('Redis')) {
        $diagnostic['class_available'] = true;
        $diagnostic['class_message'] = 'Clase Redis disponible';
    } else {
        $diagnostic['class_available'] = false;
        $diagnostic['class_message'] = 'Clase Redis NO disponible (php-redis no instalado)';
        return $diagnostic;
    }
    
    // Verificar constantes
    if (defined('REDIS_HOST')) {
        $diagnostic['host_defined'] = true;
        $diagnostic['host_value'] = REDIS_HOST;
    } else {
        $diagnostic['host_defined'] = false;
        $diagnostic['host_value'] = 'No definido';
    }
    
    if (defined('REDIS_PORT')) {
        $diagnostic['port_defined'] = true;
        $diagnostic['port_value'] = REDIS_PORT;
    } else {
        $diagnostic['port_defined'] = false;
        $diagnostic['port_value'] = 'No definido';
    }
    
    // Intentar conectar
    $diagnostic['connection_attempted'] = true;
    try {
        $redis = new Redis();
        $connected = @$redis->connect(
            defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1',
            defined('REDIS_PORT') ? REDIS_PORT : 6379,
            2 // Timeout de 2 segundos para diagnóstico
        );
        
        if ($connected) {
            $diagnostic['connection_success'] = true;
            $diagnostic['connection_message'] = 'Conexión exitosa';
            
            // Intentar ping
            try {
                $ping = $redis->ping();
                $diagnostic['ping_success'] = true;
                $diagnostic['ping_result'] = $ping;
            } catch (Exception $e) {
                $diagnostic['ping_success'] = false;
                $diagnostic['ping_error'] = $e->getMessage();
            }
            
            $redis->close();
        } else {
            $diagnostic['connection_success'] = false;
            $diagnostic['connection_message'] = 'No se pudo conectar a Redis';
            $diagnostic['connection_error'] = 'Verifica que Redis esté corriendo: redis-cli ping';
        }
    } catch (Exception $e) {
        $diagnostic['connection_success'] = false;
        $diagnostic['connection_message'] = 'Error al intentar conectar';
        $diagnostic['connection_error'] = $e->getMessage();
    }
    
    return $diagnostic;
}

/**
 * Obtener valor de Redis con fallback a wp_cache_get
 * 
 * @param string $key Clave del cache
 * @param string $group Grupo del cache
 * @return mixed|false Valor del cache o false si no existe
 */
function snn_redis_get($key, $group = 'default') {
    // Primero intentar con wp_cache_get (si Redis Object Cache está activo)
    if (function_exists('wp_cache_get')) {
        $value = wp_cache_get($key, $group);
        if ($value !== false) {
            return $value;
        }
    }
    
    // Fallback: usar Redis directamente
    $redis = snn_redis_connect();
    if (!$redis) {
        return false;
    }
    
    try {
        $full_key = REDIS_OBJECT_CACHE_PREFIX . $group . ':' . $key;
        // IMPORTANTE: Redis ya tiene OPT_SERIALIZER_PHP configurado, así que get() ya deserializa automáticamente
        // NO hacer unserialize() manualmente aquí
        $value = $redis->get($full_key);
        
        // Log para diagnóstico si WP_DEBUG está activo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $value_type = $value !== false ? gettype($value) : 'false';
            $value_info = $value !== false && is_array($value) ? (isset($value['posts']) ? 'array con posts' : 'array sin posts') : $value_type;
            error_log("SNN Redis GET: key='{$full_key}', result_type={$value_info}");
        }
        
        return $value !== false ? $value : false;
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SNN Redis GET error: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Guardar valor en Redis con fallback a wp_cache_set
 * 
 * @param string $key Clave del cache
 * @param mixed $value Valor a guardar
 * @param string $group Grupo del cache
 * @param int $expiration Tiempo de expiración en segundos (0 = sin expiración)
 * @return bool True si se guardó correctamente
 */
function snn_redis_set($key, $value, $group = 'default', $expiration = 0) {
    // Primero intentar con wp_cache_set (si Redis Object Cache está activo)
    if (function_exists('wp_cache_set')) {
        return wp_cache_set($key, $value, $group, $expiration);
    }
    
    // Fallback: usar Redis directamente
    $redis = snn_redis_connect();
    if (!$redis) {
        return false;
    }
    
    try {
        $full_key = REDIS_OBJECT_CACHE_PREFIX . $group . ':' . $key;
        // IMPORTANTE: Redis ya tiene OPT_SERIALIZER_PHP configurado, así que set() serializa automáticamente
        // NO hacer serialize() manualmente aquí para evitar doble serialización
        
        if ($expiration > 0) {
            return $redis->setex($full_key, $expiration, $value);
        } else {
            return $redis->set($full_key, $value);
        }
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SNN Redis SET error: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Eliminar valor de Redis
 * 
 * @param string $key Clave del cache
 * @param string $group Grupo del cache
 * @return bool True si se eliminó correctamente
 */
function snn_redis_delete($key, $group = 'default') {
    // Primero intentar con wp_cache_delete (si Redis Object Cache está activo)
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete($key, $group);
    }
    
    // También eliminar directamente de Redis
    $redis = snn_redis_connect();
    if (!$redis) {
        return false;
    }
    
    try {
        $full_key = REDIS_OBJECT_CACHE_PREFIX . $group . ':' . $key;
        return $redis->del($full_key) > 0;
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SNN Redis DELETE error: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Limpiar todo el cache de un grupo específico
 * 
 * @param string $group Grupo del cache (opcional, si es null limpia todo)
 * @return int Número de claves eliminadas
 */
function snn_redis_flush_group($group = null) {
    $redis = snn_redis_connect();
    if (!$redis) {
        return 0;
    }
    
    try {
        if ($group === null) {
            // Limpiar todo el cache del sitio
            $pattern = REDIS_OBJECT_CACHE_PREFIX . '*';
        } else {
            // Limpiar solo un grupo específico
            $pattern = REDIS_OBJECT_CACHE_PREFIX . $group . ':*';
        }
        
        $keys = $redis->keys($pattern);
        if (empty($keys)) {
            return 0;
        }
        
        return $redis->del($keys);
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SNN Redis FLUSH error: " . $e->getMessage());
        }
        return 0;
    }
}

/**
 * Buscar claves en Redis usando un patrón
 * 
 * @param string $pattern Patrón de búsqueda (ej: 'bl_cached_query_*')
 * @param string $group Grupo del cache
 * @return array Array de claves encontradas (sin el prefijo completo)
 */
function snn_redis_find_keys( $pattern, $group = 'default' ) {
    $redis = snn_redis_connect();
    if ( !$redis ) {
        return [];
    }
    
    try {
        // Construir el patrón completo con el prefijo
        $full_pattern = REDIS_OBJECT_CACHE_PREFIX . $group . ':' . $pattern;
        $keys = $redis->keys( $full_pattern );
        
        if ( empty( $keys ) ) {
            return [];
        }
        
        // Remover el prefijo completo para retornar solo las claves
        $prefix_length = strlen( REDIS_OBJECT_CACHE_PREFIX . $group . ':' );
        $clean_keys = [];
        foreach ( $keys as $key ) {
            if ( strlen( $key ) > $prefix_length ) {
                $clean_keys[] = substr( $key, $prefix_length );
            }
        }
        
        return $clean_keys;
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "SNN Redis FIND KEYS error: " . $e->getMessage() );
        }
        return [];
    }
}

/**
 * Obtener estadísticas de Redis
 * 
 * @return array|false Estadísticas o false si falla
 */
function snn_redis_get_stats() {
    $redis = snn_redis_connect();
    if (!$redis) {
        return false;
    }
    
    try {
        $info = $redis->info();
        return [
            'connected_clients' => $info['connected_clients'] ?? 0,
            'used_memory_human' => $info['used_memory_human'] ?? '0B',
            'used_memory_peak_human' => $info['used_memory_peak_human'] ?? '0B',
            'keyspace_hits' => $info['keyspace_hits'] ?? 0,
            'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            'total_keys' => $redis->dbSize(),
        ];
    } catch (Exception $e) {
        return false;
    }
}

