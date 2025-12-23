<?php
/**
 * Cache Admin Page
 * ================
 * 
 * Página de administración para gestionar y limpiar cache manualmente
 * 
 * @package SNN Theme
 * @since 1.0.0
 */

// Cargar helpers necesarios
if ( !function_exists( 'snn_redis_is_available' ) ) {
    $redis_helper = get_stylesheet_directory() . '/includes/redis-cache-helper.php';
    if ( file_exists( $redis_helper ) ) {
        require_once $redis_helper;
    }
}

if ( !function_exists( 'snn_purge_all_caches' ) ) {
    $purge_helper = get_stylesheet_directory() . '/includes/cache-purge-helper.php';
    if ( file_exists( $purge_helper ) ) {
        require_once $purge_helper;
    }
}

/**
 * Agregar página de administración para cache
 */
add_action( 'admin_menu', 'snn_add_cache_admin_page' );
function snn_add_cache_admin_page() {
    add_submenu_page(
        'tools.php',
        'Gestión de Cache',
        'Cache Redis/Varnish',
        'manage_options',
        'snn-cache-management',
        'snn_cache_admin_page_callback'
    );
}

/**
 * Función de ayuda para verificar si hay cache activo (para uso en AJAX o otros contextos)
 * 
 * @return array Información sobre caches activos
 */
function snn_get_active_cached_queries_info() {
    $active_cache_ids = [];
    $cache_info = [];
    
    // Verificar en variable global
    if ( function_exists( 'bl_get_cached_query_ids' ) ) {
        $active_cache_ids = bl_get_cached_query_ids();
    }
    
    // Verificar también en Redis si está disponible
    $redis_available = function_exists( 'snn_redis_is_available' ) ? snn_redis_is_available() : false;
    
    if ( function_exists( 'snn_redis_get' ) && $redis_available ) {
        // Método 1: Intentar obtener el storage completo de Redis
        $redis_storage = snn_redis_get( 'bl_cached_queries_storage', 'cached_queries' );
        if ( $redis_storage !== false && is_array( $redis_storage ) && isset( $redis_storage['posts'] ) ) {
            $redis_cache_ids = array_keys( $redis_storage['posts'] );
            $active_cache_ids = array_unique( array_merge( $active_cache_ids, $redis_cache_ids ) );
            
            foreach ( $redis_cache_ids as $cache_id ) {
                $cache_data = snn_redis_get( 'bl_cached_query_' . $cache_id, 'cached_queries' );
                if ( $cache_data !== false && is_array( $cache_data ) && isset( $cache_data['posts'] ) ) {
                    $cache_info[$cache_id] = [
                        'source' => 'Redis',
                        'posts_count' => count( $cache_data['posts'] ),
                    ];
                }
            }
        }
        
        // Método 2: Buscar directamente en Redis todas las claves de cached queries
        if ( function_exists( 'snn_redis_find_keys' ) ) {
            $redis_keys = snn_redis_find_keys( 'bl_cached_query_*', 'cached_queries' );
            if ( !empty( $redis_keys ) ) {
                foreach ( $redis_keys as $key ) {
                    if ( preg_match( '/bl_cached_query_(.+)$/', $key, $matches ) ) {
                        $cache_id = $matches[1];
                        if ( !in_array( $cache_id, $active_cache_ids ) ) {
                            $active_cache_ids[] = $cache_id;
                        }
                        
                        if ( !isset( $cache_info[$cache_id] ) ) {
                            $cache_data = snn_redis_get( 'bl_cached_query_' . $cache_id, 'cached_queries' );
                            if ( $cache_data !== false && is_array( $cache_data ) && isset( $cache_data['posts'] ) ) {
                                $cache_info[$cache_id] = [
                                    'source' => 'Redis (búsqueda directa)',
                                    'posts_count' => count( $cache_data['posts'] ),
                                ];
                            }
                        }
                    }
                }
            }
        }
    }
    
    // También verificar variable global
    global $bl_cached_queries_storage;
    if ( isset( $bl_cached_queries_storage['posts'] ) && is_array( $bl_cached_queries_storage['posts'] ) ) {
        foreach ( $bl_cached_queries_storage['posts'] as $cache_id => $posts ) {
            if ( !isset( $cache_info[$cache_id] ) ) {
                $cache_info[$cache_id] = [
                    'source' => 'Variable Global',
                    'posts_count' => count( $posts ),
                ];
            } else {
                $cache_info[$cache_id]['source'] = 'Redis + Variable Global';
            }
        }
    }
    
    return [
        'cache_ids' => $active_cache_ids,
        'cache_info' => $cache_info,
        'has_cache' => !empty( $active_cache_ids ),
    ];
}

/**
 * Callback de la página de administración
 */
function snn_cache_admin_page_callback() {
    // Procesar acciones
    if ( isset( $_POST['snn_cache_action'] ) && check_admin_referer( 'snn_cache_action' ) ) {
        $action = sanitize_text_field( $_POST['snn_cache_action'] );
        $results = [];
        
        switch ( $action ) {
            case 'purge_all':
                // Limpiar todo
                $results = [];
                if ( function_exists( 'snn_purge_all_caches' ) ) {
                    $results = snn_purge_all_caches();
                } else {
                    // Fallback manual si la función no está disponible
                    if ( function_exists( 'bl_clear_cached_query' ) ) {
                        bl_clear_cached_query();
                        $results['queries'] = true;
                    }
                    if ( function_exists( 'wp_cache_flush' ) ) {
                        $results['wordpress'] = wp_cache_flush();
                    }
                }
                
                // Contar éxitos
                $success_count = count( array_filter( $results ) );
                $total_count = count( $results );
                
                if ( $success_count > 0 ) {
                    $message = '✅ Cache limpiado exitosamente (' . $success_count . '/' . $total_count . ' sistemas)';
                } else {
                    $message = '⚠️ No se pudo limpiar ningún sistema de cache';
                }
                break;
                
            case 'purge_redis':
                // Limpiar solo Redis
                $redis_cleared = false;
                if ( function_exists( 'bl_clear_cached_query' ) ) {
                    bl_clear_cached_query();
                    $redis_cleared = true;
                }
                if ( function_exists( 'snn_redis_flush_group' ) ) {
                    $flushed = snn_redis_flush_group( 'cached_queries' );
                    $redis_cleared = true;
                }
                if ( function_exists( 'wp_cache_flush' ) ) {
                    wp_cache_flush();
                    $redis_cleared = true;
                }
                $message = $redis_cleared ? '✅ Cache de Redis limpiado exitosamente' : '⚠️ No se pudo limpiar Redis';
                break;
                
            case 'purge_varnish':
                // Limpiar solo Varnish
                if ( function_exists( 'snn_purge_varnish_urls' ) ) {
                    $purge_result = snn_purge_varnish_urls( [ home_url() ], true ); // true = retornar detalles
                    if ( is_array( $purge_result ) ) {
                        $purged = $purge_result['purged'];
                        $details = $purge_result['details'];
                        if ( $purged > 0 ) {
                            $message = '✅ Cache de Varnish limpiado exitosamente';
                        } else {
                            // Construir mensaje detallado con información del error
                            $error_msg = '⚠️ No se pudo limpiar Varnish.';
                            if ( !empty( $details ) ) {
                                $error_details = [];
                                foreach ( $details as $detail ) {
                                    if ( isset( $detail['error'] ) ) {
                                        $error_details[] = $detail['error'];
                                    }
                                }
                                if ( !empty( $error_details ) ) {
                                    $error_msg .= ' Detalles: ' . implode( '; ', $error_details );
                                }
                            }
                            $message = $error_msg . ' Limpia manualmente desde CloudPanel si es necesario.';
                        }
                    } else {
                        // Fallback al comportamiento anterior
                        $purged = is_numeric( $purge_result ) ? $purge_result : 0;
                        if ( $purged > 0 ) {
                            $message = '✅ Cache de Varnish limpiado exitosamente';
                        } else {
                            // Verificar si curl está disponible
                            if ( !function_exists( 'curl_init' ) ) {
                                $message = '⚠️ No se pudo limpiar Varnish: curl no está disponible. Limpia manualmente desde CloudPanel.';
                            } else {
                                $message = '⚠️ No se pudo limpiar Varnish. Puede requerir configuración en Varnish para aceptar PURGE. Limpia manualmente desde CloudPanel.';
                            }
                        }
                    }
                } else {
                    $message = '⚠️ Función de limpieza de Varnish no disponible';
                }
                break;
                
            case 'purge_queries':
                // Limpiar solo queries cacheadas
                if ( function_exists( 'bl_clear_cached_query' ) ) {
                    bl_clear_cached_query();
                }
                $message = '✅ Queries cacheadas limpiadas exitosamente';
                break;
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        
        if ( !empty( $results ) ) {
            $success_count = count( array_filter( $results ) );
            $total_count = count( $results );
            
            if ( $success_count < $total_count ) {
                // Solo mostrar resultados detallados si hubo algunos fallos
                echo '<div class="notice notice-info"><p><strong>Resultados detallados:</strong></p><ul>';
                foreach ( $results as $system => $success ) {
                    $icon = $success ? '✅' : '❌';
                    $system_name = ucfirst( $system );
                    // Traducir nombres de sistemas
                    $system_names = [
                        'redis' => 'Redis',
                        'varnish' => 'Varnish',
                        'nginx' => 'Nginx',
                        'wordpress' => 'WordPress',
                        'queries' => 'Queries Cacheadas',
                    ];
                    $system_name = isset( $system_names[strtolower( $system )] ) ? $system_names[strtolower( $system )] : $system_name;
                    echo '<li>' . $icon . ' ' . esc_html( $system_name ) . '</li>';
                }
                echo '</ul></div>';
            }
        }
    }
    
    // Obtener estadísticas con mejor diagnóstico
    $redis_stats = false;
    $redis_available = false;
    $redis_diagnostic = [];
    
    // Usar función de diagnóstico mejorada si está disponible
    if ( function_exists( 'snn_redis_get_diagnostic' ) ) {
        $diagnostic = snn_redis_get_diagnostic();
        
        // Construir mensajes de diagnóstico
        if ( isset( $diagnostic['class_available'] ) ) {
            $redis_diagnostic[] = $diagnostic['class_available'] 
                ? '✅ ' . $diagnostic['class_message']
                : '❌ ' . $diagnostic['class_message'];
        }
        
        if ( isset( $diagnostic['host_defined'] ) ) {
            $redis_diagnostic[] = $diagnostic['host_defined']
                ? '✅ REDIS_HOST: ' . $diagnostic['host_value']
                : '⚠️ REDIS_HOST: ' . $diagnostic['host_value'];
        }
        
        if ( isset( $diagnostic['port_defined'] ) ) {
            $redis_diagnostic[] = $diagnostic['port_defined']
                ? '✅ REDIS_PORT: ' . $diagnostic['port_value']
                : '⚠️ REDIS_PORT: ' . $diagnostic['port_value'];
        }
        
        if ( isset( $diagnostic['connection_success'] ) ) {
            if ( $diagnostic['connection_success'] ) {
                $redis_diagnostic[] = '✅ ' . $diagnostic['connection_message'];
                if ( isset( $diagnostic['ping_success'] ) && $diagnostic['ping_success'] ) {
                    $redis_diagnostic[] = '✅ Ping exitoso: ' . ( is_string( $diagnostic['ping_result'] ) ? $diagnostic['ping_result'] : 'OK' );
                }
                $redis_available = true;
                if ( function_exists( 'snn_redis_get_stats' ) ) {
                    $redis_stats = snn_redis_get_stats();
                }
            } else {
                $redis_diagnostic[] = '❌ ' . $diagnostic['connection_message'];
                if ( isset( $diagnostic['connection_error'] ) ) {
                    $redis_diagnostic[] = '💡 ' . $diagnostic['connection_error'];
                }
            }
        }
    } else {
        // Fallback al método anterior
        if ( !class_exists( 'Redis' ) ) {
            $redis_diagnostic[] = '❌ Clase Redis no disponible (php-redis no instalado)';
        } else {
            $redis_diagnostic[] = '✅ Clase Redis disponible';
            
            if ( !defined( 'REDIS_HOST' ) ) {
                $redis_diagnostic[] = '⚠️ REDIS_HOST no definido en wp-config.php';
            } else {
                $redis_diagnostic[] = '✅ REDIS_HOST: ' . REDIS_HOST;
            }
            
            if ( !defined( 'REDIS_PORT' ) ) {
                $redis_diagnostic[] = '⚠️ REDIS_PORT no definido en wp-config.php';
            } else {
                $redis_diagnostic[] = '✅ REDIS_PORT: ' . REDIS_PORT;
            }
            
            if ( function_exists( 'snn_redis_is_available' ) ) {
                $redis_available = snn_redis_is_available();
                if ( $redis_available ) {
                    $redis_diagnostic[] = '✅ Conexión exitosa';
                    if ( function_exists( 'snn_redis_get_stats' ) ) {
                        $redis_stats = snn_redis_get_stats();
                    }
                } else {
                    $redis_diagnostic[] = '❌ No se pudo conectar a Redis';
                    $redis_diagnostic[] = '💡 Verifica que Redis esté corriendo: redis-cli ping';
                }
            }
        }
    }
    
    // Obtener cache IDs activos usando la función de ayuda
    $cache_data = snn_get_active_cached_queries_info();
    $active_cache_ids = $cache_data['cache_ids'];
    $cache_info = $cache_data['cache_info'];
    
    ?>
    <div class="wrap">
        <h1>🚀 Gestión de Cache</h1>
        <p>Gestiona y limpia el cache de Redis, Varnish y queries de WordPress.</p>
        
        <div class="card" style="max-width: 1200px;">
            <h2>Estado del Sistema</h2>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Sistema</th>
                        <th>Estado</th>
                        <th>Información</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Redis</strong></td>
                        <td>
                            <?php if ( $redis_available ): ?>
                                <span style="color: green;">✅ Disponible</span>
                            <?php else: ?>
                                <span style="color: red;">❌ No disponible</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $redis_stats ): ?>
                                Memoria: <?php echo esc_html( $redis_stats['used_memory_human'] ); ?> | 
                                Claves: <?php echo esc_html( $redis_stats['total_keys'] ); ?> | 
                                Hits: <?php echo esc_html( number_format( $redis_stats['keyspace_hits'] ) ); ?> | 
                                Misses: <?php echo esc_html( number_format( $redis_stats['keyspace_misses'] ) ); ?>
                            <?php else: ?>
                                <details>
                                    <summary style="cursor: pointer; color: #0073aa;">Ver diagnóstico</summary>
                                    <ul style="margin-top: 10px;">
                                        <?php foreach ( $redis_diagnostic as $msg ): ?>
                                            <li><?php echo esc_html( $msg ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Varnish</strong></td>
                        <td>
                            <?php 
                            $varnish_available = function_exists( 'varnish_http_purge' ) || function_exists( 'snn_purge_varnish_urls' );
                            if ( $varnish_available ): ?>
                                <span style="color: green;">✅ Disponible</span>
                            <?php else: ?>
                                <span style="color: orange;">⚠️ Limpieza manual desde CloudPanel</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( function_exists( 'varnish_http_purge' ) ): ?>
                                Función de limpieza disponible (plugin)
                            <?php elseif ( function_exists( 'snn_purge_varnish_urls' ) ): ?>
                                Limpieza disponible vía HTTP PURGE (sin plugin)
                            <?php else: ?>
                                Limpia manualmente desde CloudPanel → Varnish Cache → Purge Cache
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Cached WP Query</strong></td>
                        <td>
                            <span style="color: green;">✅ Activo</span>
                        </td>
                        <td>
                            <?php if ( !empty( $active_cache_ids ) ): ?>
                                <strong>Cache IDs activos:</strong> <?php echo esc_html( implode( ', ', $active_cache_ids ) ); ?>
                                <?php if ( !empty( $cache_info ) ): ?>
                                    <br><small>
                                    <?php foreach ( $cache_info as $cache_id => $info ): ?>
                                        • <?php echo esc_html( $cache_id ); ?>: <?php echo esc_html( $info['posts_count'] ); ?> posts (<?php echo esc_html( $info['source'] ); ?>)
                                    <?php endforeach; ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <div>
                                    <strong>No hay cache activo en este momento</strong>
                                    <br><small style="color: #666;">
                                        ℹ️ El cache solo se crea cuando se visita una página que usa Cached WP Query. 
                                        Si acabas de configurar una página con este sistema, visita esa página primero para que se genere el cache.
                                        <br>
                                        El cache se guarda en Redis con TTL de 1 hora y se limpia automáticamente cuando publicas/actualizas contenido.
                                    </small>
                                    <?php if ( $redis_available ): ?>
                                        <br><small style="color: #666;">
                                            ✅ Redis está disponible - El cache se guardará automáticamente cuando se use.
                                        </small>
                                    <?php else: ?>
                                        <br><small style="color: #d63638;">
                                            ⚠️ Redis no está disponible - El cache solo funcionará en memoria durante la petición actual.
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>Limpiar Cache</h2>
            <p>Selecciona qué tipo de cache deseas limpiar:</p>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'snn_cache_action' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Limpiar Todo</th>
                        <td>
                            <p>Limpia Redis, Varnish, Nginx y todas las queries cacheadas.</p>
                            <button type="submit" name="snn_cache_action" value="purge_all" class="button button-primary">
                                🗑️ Limpiar Todo el Cache
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Limpiar Solo Redis</th>
                        <td>
                            <p>Limpia solo el cache de Redis (object cache y queries cacheadas).</p>
                            <button type="submit" name="snn_cache_action" value="purge_redis" class="button">
                                🔴 Limpiar Redis
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Limpiar Solo Varnish</th>
                        <td>
                            <p>Limpia solo el cache de Varnish (páginas HTML completas).</p>
                            <button type="submit" name="snn_cache_action" value="purge_varnish" class="button">
                                🟢 Limpiar Varnish
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Limpiar Solo Queries</th>
                        <td>
                            <p>Limpia solo las queries cacheadas del sistema Cached WP Query.</p>
                            <button type="submit" name="snn_cache_action" value="purge_queries" class="button">
                                🔵 Limpiar Queries Cacheadas
                            </button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>Información</h2>
            <ul>
                <li><strong>Cache automático:</strong> El cache se limpia automáticamente cuando publicas, actualizas o eliminas un post.</li>
                <li><strong>TTL de Redis:</strong> Las queries cacheadas expiran después de 1 hora automáticamente.</li>
                <li><strong>Varnish:</strong> El cache de Varnish se limpia automáticamente al publicar contenido.</li>
                <li><strong>Compatibilidad:</strong> El sistema es compatible con tu sistema de Cached WP Query existente.</li>
            </ul>
        </div>
        
        <?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ): ?>
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>🔍 Diagnóstico de Cached WP Query</h2>
            <p>Esta sección solo se muestra cuando <code>WP_DEBUG</code> está activo.</p>
            
            <h3>Verificar configuración en Bricks:</h3>
            <ol>
                <li>Abre Bricks Builder en la página que usa Cached WP Query</li>
                <li>Selecciona el elemento con Query Loop</li>
                <li>En la pestaña <strong>CONTENT</strong> → <strong>Query</strong>, verifica:
                    <ul>
                        <li>✅ <strong>Type:</strong> Debe estar en "Cached WP Query"</li>
                        <li>✅ <strong>Cache ID:</strong> Debe tener un valor (ej: "homepage", "QueryHome100", etc.)</li>
                        <li>✅ <strong>Query Arguments:</strong> Debe tener los argumentos de la query configurados</li>
                    </ul>
                </li>
            </ol>
            
            <h3>Verificar logs del servidor:</h3>
            <p>Si <code>WP_DEBUG</code> está activo, revisa los logs del servidor (generalmente en <code>wp-content/debug.log</code>) para ver mensajes como:</p>
            <ul>
                <li><code>Cached WP Query: Procesando cache_id: [tu_cache_id]</code> - Indica que el sistema está procesando el cache</li>
                <li><code>Cached WP Query: Cache guardado en Redis...</code> - Indica que el cache se guardó exitosamente</li>
                <li><code>Cached WP Query: cache_id no configurado...</code> - Indica que falta configurar el Cache ID en Bricks</li>
            </ul>
            
            <h3>Probar manualmente:</h3>
            <p>Visita la página que usa Cached WP Query y luego vuelve a esta página para ver si aparece el cache activo.</p>
            
            <?php
            // Mostrar información de diagnóstico adicional
            global $bl_cached_queries_storage;
            if ( isset( $bl_cached_queries_storage ) ) {
                echo '<h3>Estado actual en memoria:</h3>';
                echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto;">';
                echo 'Cache IDs en variable global: ' . ( isset( $bl_cached_queries_storage['posts'] ) ? count( $bl_cached_queries_storage['posts'] ) : 0 ) . "\n";
                if ( isset( $bl_cached_queries_storage['posts'] ) && !empty( $bl_cached_queries_storage['posts'] ) ) {
                    foreach ( $bl_cached_queries_storage['posts'] as $cache_id => $posts ) {
                        echo "  - {$cache_id}: " . count( $posts ) . " posts\n";
                    }
                }
                echo '</pre>';
            }
            
            // Verificar en Redis directamente
            if ( $redis_available && function_exists( 'snn_redis_find_keys' ) ) {
                $redis_keys = snn_redis_find_keys( 'bl_cached_query_*', 'cached_queries' );
                echo '<h3>Claves encontradas en Redis:</h3>';
                if ( !empty( $redis_keys ) ) {
                    echo '<ul>';
                    foreach ( $redis_keys as $key ) {
                        $cache_data = snn_redis_get( $key, 'cached_queries' );
                        $posts_count = ( $cache_data && isset( $cache_data['posts'] ) ) ? count( $cache_data['posts'] ) : 0;
                        echo '<li><code>' . esc_html( $key ) . '</code>: ' . $posts_count . ' posts</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>No se encontraron claves de cache en Redis.</p>';
                }
            }
            ?>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
        }
        .card h2 {
            margin-top: 0;
        }
        .widefat th, .widefat td {
            padding: 10px;
        }
    </style>
    <?php
}

