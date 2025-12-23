<?php
/**
 * Cache Purge Helper
 * ===================
 * 
 * Sistema de ayuda para limpiar cache en múltiples sistemas:
 * - Redis (Object Cache)
 * - Varnish (Page Cache)
 * - Nginx FastCGI Cache
 * - WordPress Transients
 * 
 * @package SNN Theme
 * @since 1.0.0
 */

/**
 * Limpiar cache de Varnish para una URL específica
 * 
 * @param string $url URL a limpiar (opcional, si es null limpia homepage)
 * @return bool True si se limpió correctamente
 */
function snn_purge_varnish_cache( $url = null ) {
    if ( !function_exists( 'varnish_http_purge' ) ) {
        return false;
    }
    
    if ( $url === null ) {
        $url = home_url();
    }
    
    try {
        return varnish_http_purge( $url );
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'SNN Varnish Purge Error: ' . $e->getMessage() );
        }
        return false;
    }
}

/**
 * Limpiar cache de Varnish usando HTTP PURGE
 * 
 * @param string|array $urls URL o array de URLs a limpiar
 * @return int Número de URLs limpiadas exitosamente
 */
function snn_purge_varnish_urls( $urls ) {
    if ( !is_array( $urls ) ) {
        $urls = [ $urls ];
    }
    
    $purged = 0;
    
    foreach ( $urls as $url ) {
        // Intentar con función de plugin primero
        if ( function_exists( 'varnish_http_purge' ) ) {
            if ( snn_purge_varnish_cache( $url ) ) {
                $purged++;
            }
        } else {
            // Fallback: hacer request HTTP PURGE directamente (sin plugin)
            // Nota: Esto requiere que Varnish esté configurado para aceptar PURGE desde localhost
            if ( !function_exists( 'curl_init' ) ) {
                // Si curl no está disponible, no podemos hacer PURGE automático
                continue;
            }
            
            $parsed_url = parse_url( $url );
            $host = isset( $parsed_url['host'] ) ? $parsed_url['host'] : ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost' );
            $scheme = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] : 'http';
            $path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';
            
            // Construir URL completa
            $full_url = $scheme . '://' . $host . $path;
            
            $ch = curl_init( $full_url );
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PURGE' );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_TIMEOUT, 2 );
            curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 1 );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, [
                'Host: ' . $host,
            ] );
            
            $response = curl_exec( $ch );
            $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            $curl_error = curl_error( $ch );
            curl_close( $ch );
            
            if ( $http_code === 200 || $http_code === 204 ) {
                $purged++;
            } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SNN Varnish PURGE failed for ' . $full_url . ': HTTP ' . $http_code . ( $curl_error ? ' - ' . $curl_error : '' ) );
            }
        }
    }
    
    return $purged;
}

/**
 * Limpiar cache de Nginx FastCGI
 * 
 * @return bool True si se limpió correctamente
 */
function snn_purge_nginx_cache() {
    // Nginx FastCGI cache se limpia eliminando archivos del directorio de cache
    // Esto requiere acceso al sistema de archivos
    
    $cache_paths = [
        '/var/cache/nginx',
        '/var/run/nginx-cache',
        getenv( 'NGINX_CACHE_PATH' ),
    ];
    
    $purged = false;
    
    foreach ( $cache_paths as $cache_path ) {
        if ( $cache_path && is_dir( $cache_path ) && is_writable( $cache_path ) ) {
            // Limpiar archivos de cache (más seguro que eliminar todo el directorio)
            $files = glob( $cache_path . '/*' );
            foreach ( $files as $file ) {
                if ( is_file( $file ) ) {
                    @unlink( $file );
                    $purged = true;
                }
            }
        }
    }
    
    return $purged;
}

/**
 * Limpiar todos los sistemas de cache
 * 
 * @param int|null $post_id ID del post (opcional, para limpiar URLs específicas)
 * @return array Resultados de la limpieza
 */
function snn_purge_all_caches( $post_id = null ) {
    $results = [
        'redis' => false,
        'varnish' => false,
        'nginx' => false,
        'wordpress' => false,
    ];
    
    // 1. Limpiar Redis
    if ( function_exists( 'snn_redis_flush_group' ) ) {
        $results['redis'] = snn_redis_flush_group( 'cached_queries' ) > 0;
    }
    
    // 2. Limpiar WordPress transients y object cache
    if ( function_exists( 'wp_cache_flush' ) ) {
        $results['wordpress'] = wp_cache_flush();
    }
    
    // 3. Limpiar Varnish
    $varnish_urls = [ home_url() ];
    
    if ( $post_id ) {
        $varnish_urls[] = get_permalink( $post_id );
        $varnish_urls[] = home_url(); // Homepage también
    }
    
    $results['varnish'] = snn_purge_varnish_urls( $varnish_urls ) > 0;
    
    // 4. Limpiar Nginx FastCGI Cache
    $results['nginx'] = snn_purge_nginx_cache();
    
    // Hook para otros sistemas
    do_action( 'snn_all_caches_purged', $post_id, $results );
    
    return $results;
}

/**
 * Limpiar cache cuando se publica/actualiza un post
 * 
 * @param int $post_id ID del post
 * @param WP_Post $post Objeto del post
 */
function snn_purge_cache_on_post_save( $post_id, $post ) {
    // Evitar limpiar en autosaves y revisiones
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    
    // Solo limpiar si el post está publicado o se acaba de publicar
    if ( $post->post_status !== 'publish' ) {
        return;
    }
    
    // Limpiar todos los sistemas de cache
    snn_purge_all_caches( $post_id );
}

// Hook para limpiar cache cuando se guarda un post
add_action( 'save_post', 'snn_purge_cache_on_post_save', 99, 2 );
add_action( 'delete_post', function( $post_id ) {
    snn_purge_all_caches( $post_id );
}, 99 );

// Hook para limpiar cache cuando cambia el estado de un post
add_action( 'transition_post_status', function( $new_status, $old_status, $post ) {
    if ( $new_status === 'publish' || $old_status === 'publish' ) {
        snn_purge_all_caches( $post->ID );
    }
}, 99, 3 );

