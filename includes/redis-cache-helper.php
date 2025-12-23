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
            $redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZ4);
            
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
        return $redis->ping() === '+PONG';
    } catch (Exception $e) {
        return false;
    }
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
        $value = $redis->get($full_key);
        return $value !== false ? unserialize($value) : false;
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
        $serialized = serialize($value);
        
        if ($expiration > 0) {
            return $redis->setex($full_key, $expiration, $serialized);
        } else {
            return $redis->set($full_key, $serialized);
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

