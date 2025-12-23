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
 * Configuración opcional de Varnish (en wp-config.php):
 * define( 'VARNISH_HOST', '127.0.0.1' ); // Por defecto: 127.0.0.1
 * define( 'VARNISH_PORT', 6081 );         // Por defecto: 6081
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
 * @param bool $return_details Si es true, retorna array con detalles en lugar de solo el número
 * @return int|array Número de URLs limpiadas exitosamente, o array con detalles si $return_details es true
 */
function snn_purge_varnish_urls( $urls, $return_details = false ) {
    if ( !is_array( $urls ) ) {
        $urls = [ $urls ];
    }
    
    $purged = 0;
    $details = [];
    
    foreach ( $urls as $url ) {
        $url_detail = [
            'url' => $url,
            'success' => false,
            'error' => null,
        ];
        
        // Intentar con función de plugin primero
        if ( function_exists( 'varnish_http_purge' ) ) {
            if ( snn_purge_varnish_cache( $url ) ) {
                $purged++;
                $url_detail['success'] = true;
                $url_detail['method'] = 'plugin';
            } else {
                $url_detail['error'] = 'Función varnish_http_purge() retornó false';
            }
        } else {
            // Fallback: hacer request HTTP PURGE directamente (sin plugin)
            // Nota: Esto requiere que Varnish esté configurado para aceptar PURGE desde localhost
            if ( !function_exists( 'curl_init' ) ) {
                // Si curl no está disponible, no podemos hacer PURGE automático
                $url_detail['error'] = 'curl no está disponible en el servidor';
                $details[] = $url_detail;
                continue;
            }
            
            // Parsear URL y construir correctamente
            $parsed_url = parse_url( $url );
            
            // Si la URL no se puede parsear, intentar construirla desde home_url()
            if ( $parsed_url === false || ( !isset( $parsed_url['host'] ) && !isset( $parsed_url['path'] ) ) ) {
                $home_url = home_url();
                $parsed_url = parse_url( $home_url );
                if ( $parsed_url === false ) {
                    $url_detail['error'] = 'No se pudo parsear la URL: ' . $url;
                    $details[] = $url_detail;
                    continue;
                }
            }
            
            $host = isset( $parsed_url['host'] ) ? $parsed_url['host'] : ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost' );
            $scheme = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] : ( is_ssl() ? 'https' : 'http' );
            $path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';
            $query = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
            
            // Obtener puerto de Varnish (por defecto 6081, pero puede configurarse)
            $varnish_port = defined( 'VARNISH_PORT' ) ? VARNISH_PORT : 6081;
            $varnish_host = defined( 'VARNISH_HOST' ) ? VARNISH_HOST : '127.0.0.1';
            
            // Método 1: Intentar PURGE directamente al puerto de Varnish (localhost:6081)
            // Este es el método más común y confiable
            $varnish_url = 'http://' . $varnish_host . ':' . $varnish_port . $path . $query;
            
            $ch = curl_init( $varnish_url );
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
            
            $url_detail['method'] = 'HTTP PURGE directo (puerto Varnish)';
            $url_detail['http_code'] = $http_code;
            $url_detail['varnish_url'] = $varnish_url;
            
            // Si el método directo al puerto de Varnish falla, intentar método alternativo
            if ( $http_code !== 200 && $http_code !== 204 ) {
                // Método 2: Intentar con la URL pública (fallback)
                $full_url = $scheme . '://' . $host . $path . $query;
                
                if ( filter_var( $full_url, FILTER_VALIDATE_URL ) !== false ) {
                    $ch2 = curl_init( $full_url );
                    curl_setopt( $ch2, CURLOPT_CUSTOMREQUEST, 'PURGE' );
                    curl_setopt( $ch2, CURLOPT_RETURNTRANSFER, true );
                    curl_setopt( $ch2, CURLOPT_TIMEOUT, 2 );
                    curl_setopt( $ch2, CURLOPT_CONNECTTIMEOUT, 1 );
                    curl_setopt( $ch2, CURLOPT_HTTPHEADER, [
                        'Host: ' . $host,
                        'X-Purge-Method: PURGE',
                    ] );
                    
                    $response2 = curl_exec( $ch2 );
                    $http_code2 = curl_getinfo( $ch2, CURLINFO_HTTP_CODE );
                    $curl_error2 = curl_error( $ch2 );
                    curl_close( $ch2 );
                    
                    if ( $http_code2 === 200 || $http_code2 === 204 ) {
                        $http_code = $http_code2;
                        $curl_error = $curl_error2;
                        $url_detail['method'] = 'HTTP PURGE directo (URL pública)';
                        $url_detail['varnish_url'] = $full_url;
                    }
                }
            }
            
            if ( $http_code === 200 || $http_code === 204 ) {
                $purged++;
                $url_detail['success'] = true;
            } else {
                // Construir mensaje de error detallado
                $error_parts = [];
                if ( $http_code ) {
                    $error_parts[] = 'HTTP ' . $http_code;
                    // Agregar descripción del código HTTP
                    if ( $http_code === 400 ) {
                        $error_parts[] = '(Bad Request - Varnish puede requerir configuración específica. Intentado en: ' . $varnish_url . ')';
                    } elseif ( $http_code === 405 ) {
                        $error_parts[] = '(Método no permitido - Varnish puede no estar configurado para aceptar PURGE)';
                    } elseif ( $http_code === 403 ) {
                        $error_parts[] = '(Prohibido - Varnish puede requerir autenticación o IP permitida)';
                    } elseif ( $http_code === 404 ) {
                        $error_parts[] = '(No encontrado)';
                    } elseif ( $http_code === 0 ) {
                        $error_parts[] = '(Sin conexión - Varnish puede no estar corriendo en ' . $varnish_host . ':' . $varnish_port . ' o no ser accesible)';
                    } else {
                        $error_parts[] = '(Código HTTP inesperado)';
                    }
                }
                if ( $curl_error ) {
                    $error_parts[] = 'Error curl: ' . $curl_error;
                }
                if ( empty( $error_parts ) ) {
                    $error_parts[] = 'Respuesta inesperada';
                }
                $url_detail['error'] = implode( ' - ', $error_parts );
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'SNN Varnish PURGE failed for ' . $url . ': ' . $url_detail['error'] . ' (Intentado en: ' . $varnish_url . ')' );
                }
            }
        }
        
        if ( $return_details ) {
            $details[] = $url_detail;
        }
    }
    
    if ( $return_details ) {
        return [
            'purged' => $purged,
            'total' => count( $urls ),
            'details' => $details,
        ];
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
 * Forzar regeneración del cache de Cached WP Query haciendo una petición HTTP interna
 * Esto asegura que el cache se regenere incluso cuando Varnish está sirviendo páginas cacheadas
 * 
 * @param string|array $urls URL o array de URLs a visitar para regenerar cache
 * @return bool True si se hizo la petición correctamente
 */
function snn_warmup_cached_queries( $urls = null ) {
    if ( $urls === null ) {
        $urls = [ home_url() ];
    }
    
    if ( !is_array( $urls ) ) {
        $urls = [ $urls ];
    }
    
    if ( !function_exists( 'curl_init' ) ) {
        return false;
    }
    
    $success = false;
    
    foreach ( $urls as $url ) {
        // Hacer petición HTTP interna con header especial para forzar procesamiento
        // Usar localhost directamente para evitar pasar por Varnish
        $parsed_url = parse_url( $url );
        if ( $parsed_url === false ) {
            continue;
        }
        
        $host = isset( $parsed_url['host'] ) ? $parsed_url['host'] : 'localhost';
        $path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';
        $query = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
        
        // Intentar conectar directamente al puerto de PHP-FPM o Nginx (no Varnish)
        // Usar el puerto interno si está disponible, o localhost:80
        $internal_url = 'http://127.0.0.1' . $path . $query;
        
        $ch = curl_init( $internal_url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 30 ); // Timeout más largo para permitir generación de cache
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [
            'Host: ' . $host,
            'X-Warmup-Cache: 1', // Header especial para identificar peticiones de warmup
            'User-Agent: WordPress Cache Warmup',
        ] );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
        // Usar GET request (no HEAD) para que WordPress procese completamente la página y genere el cache
        // Pero limitar el tamaño de la respuesta para no consumir demasiada memoria
        curl_setopt( $ch, CURLOPT_MAXFILESIZE, 1024 * 1024 ); // Máximo 1MB de respuesta
        
        $response = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );
        
        if ( $http_code >= 200 && $http_code < 400 ) {
            $success = true;
        }
    }
    
    return $success;
}

/**
 * Limpiar solo Varnish (sin tocar otros caches)
 * 
 * @param int|null $post_id ID del post (opcional, para limpiar URLs específicas)
 * @param bool $warmup_cache Si es true, fuerza regeneración del cache después de limpiar Varnish
 * @return bool True si se limpió correctamente
 */
function snn_purge_varnish_only( $post_id = null, $warmup_cache = true ) {
    $varnish_urls = [ home_url() ];
    
    if ( $post_id ) {
        $varnish_urls[] = get_permalink( $post_id );
        $varnish_urls[] = home_url(); // Homepage también
    }
    
    $varnish_purged = snn_purge_varnish_urls( $varnish_urls );
    
    // Después de limpiar Varnish, forzar regeneración del cache de queries
    // Esto asegura que el cache se regenere incluso para usuarios anónimos
    if ( $varnish_purged > 0 && $warmup_cache ) {
        // Hacer warmup en background (no bloquear la respuesta)
        if ( function_exists( 'wp_schedule_single_event' ) ) {
            // Usar cron de WordPress para hacer warmup en background
            wp_schedule_single_event( time() + 2, 'snn_warmup_cached_queries', [ $varnish_urls ] );
        } else {
            // Fallback: hacer warmup directamente (puede ser más lento)
            snn_warmup_cached_queries( $varnish_urls );
        }
    }
    
    return $varnish_purged > 0;
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
    
    // 1. Limpiar Redis (queries cacheadas)
    if ( function_exists( 'bl_clear_cached_query' ) ) {
        bl_clear_cached_query();
        $results['redis'] = true; // Siempre exitoso si la función existe
    }
    
    // 2. Limpiar Redis Object Cache
    if ( function_exists( 'snn_redis_flush_group' ) ) {
        $flushed = snn_redis_flush_group( 'cached_queries' );
        $results['redis'] = $results['redis'] || ( $flushed >= 0 ); // >= 0 porque puede ser 0 si no hay cache
    }
    
    // 3. Limpiar WordPress transients y object cache
    if ( function_exists( 'wp_cache_flush' ) ) {
        $results['wordpress'] = wp_cache_flush();
    }
    
    // 4. Limpiar Varnish
    $results['varnish'] = snn_purge_varnish_only( $post_id );
    
    // 5. Limpiar Nginx FastCGI Cache
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

// Hook para hacer warmup del cache cuando se programa (via cron)
add_action( 'snn_warmup_cached_queries', function( $urls ) {
    if ( function_exists( 'snn_warmup_cached_queries' ) ) {
        snn_warmup_cached_queries( $urls );
    }
}, 10, 1 );

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

