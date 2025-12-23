<?php
/**
 * Configuración de Redis para WordPress
 * ======================================
 * 
 * INSTRUCCIONES:
 * 1. Copia estas líneas a tu archivo wp-config.php
 * 2. Ajusta los valores según tu configuración de Redis
 * 3. Asegúrate de que Redis esté instalado y funcionando
 * 
 * VERIFICAR REDIS:
 * - En terminal: redis-cli ping (debe responder PONG)
 * - En PHP: php -m | grep redis (debe mostrar redis)
 */

// ============================================
// CONFIGURACIÓN DE REDIS PARA OBJECT CACHE
// ============================================

// Host de Redis (por defecto localhost)
if ( !defined( 'WP_REDIS_HOST' ) ) {
    define( 'WP_REDIS_HOST', '127.0.0.1' );
}

// Puerto de Redis (por defecto 6379)
if ( !defined( 'WP_REDIS_PORT' ) ) {
    define( 'WP_REDIS_PORT', 6379 );
}

// Base de datos de Redis (0-15, usa 0 para object cache)
if ( !defined( 'WP_REDIS_DATABASE' ) ) {
    define( 'WP_REDIS_DATABASE', 0 );
}

// Contraseña de Redis (dejar vacío si no tiene)
if ( !defined( 'WP_REDIS_PASSWORD' ) ) {
    define( 'WP_REDIS_PASSWORD', '' );
}

// Timeout de conexión (segundos)
if ( !defined( 'WP_REDIS_TIMEOUT' ) ) {
    define( 'WP_REDIS_TIMEOUT', 1 );
}

// Timeout de lectura (segundos)
if ( !defined( 'WP_REDIS_READ_TIMEOUT' ) ) {
    define( 'WP_REDIS_READ_TIMEOUT', 1 );
}

// Prefijo para las claves de Redis (evita conflictos entre sitios)
if ( !defined( 'WP_REDIS_PREFIX' ) ) {
    // Usar el nombre de la base de datos como prefijo
    $redis_prefix = defined( 'DB_NAME' ) ? DB_NAME : 'lanacion';
    define( 'WP_REDIS_PREFIX', $redis_prefix . ':' );
}

// Compresión de datos (reduce uso de memoria)
if ( !defined( 'WP_REDIS_COMPRESSION' ) ) {
    define( 'WP_REDIS_COMPRESSION', true );
}

// Serialización (php es más rápido, igbinary es más eficiente en memoria)
if ( !defined( 'WP_REDIS_SERIALIZER' ) ) {
    define( 'WP_REDIS_SERIALIZER', 'php' ); // Opciones: php, igbinary
}

// ============================================
// CONFIGURACIÓN ESPECÍFICA PARA NUESTRO TEMA
// ============================================

// Prefijo para el contador de visitas (usa base de datos diferente)
if ( !defined( 'REDIS_HOST' ) ) {
    define( 'REDIS_HOST', '127.0.0.1' );
}

if ( !defined( 'REDIS_PORT' ) ) {
    define( 'REDIS_PORT', 6379 );
}

if ( !defined( 'REDIS_SITE_PREFIX' ) ) {
    $site_prefix = defined( 'DB_NAME' ) ? DB_NAME : 'lanacion';
    define( 'REDIS_SITE_PREFIX', $site_prefix . '_visits_' );
}

// ============================================
// HABILITAR REDIS OBJECT CACHE
// ============================================

// Si tienes el plugin Redis Object Cache instalado, descomenta esta línea:
// define( 'WP_REDIS_DISABLED', false );

// Si NO tienes el plugin pero quieres usar nuestro sistema de cache:
// Nuestro sistema funciona sin el plugin, usando Redis directamente

// ============================================
// VERIFICACIÓN DE REDIS (OPCIONAL)
// ============================================

// Descomenta estas líneas temporalmente para verificar que Redis funciona:
/*
if ( class_exists( 'Redis' ) ) {
    $redis = new Redis();
    try {
        $connected = $redis->connect( WP_REDIS_HOST, WP_REDIS_PORT, 1 );
        if ( $connected ) {
            error_log( '✅ Redis conectado correctamente en ' . WP_REDIS_HOST . ':' . WP_REDIS_PORT );
        } else {
            error_log( '❌ No se pudo conectar a Redis' );
        }
    } catch ( Exception $e ) {
        error_log( '❌ Error de conexión Redis: ' . $e->getMessage() );
    }
} else {
    error_log( '⚠️ La extensión PHP Redis no está instalada. Instala php-redis.' );
}
*/

