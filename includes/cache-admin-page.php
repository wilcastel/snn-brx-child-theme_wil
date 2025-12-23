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
                if ( function_exists( 'bl_clear_cached_query' ) ) {
                    bl_clear_cached_query();
                }
                if ( function_exists( 'snn_purge_all_caches' ) ) {
                    $results = snn_purge_all_caches();
                }
                $message = '✅ Cache limpiado exitosamente';
                break;
                
            case 'purge_redis':
                // Limpiar solo Redis
                if ( function_exists( 'bl_clear_cached_query' ) ) {
                    bl_clear_cached_query();
                }
                if ( function_exists( 'snn_redis_flush_group' ) ) {
                    snn_redis_flush_group( 'cached_queries' );
                }
                if ( function_exists( 'wp_cache_flush' ) ) {
                    wp_cache_flush();
                }
                $message = '✅ Cache de Redis limpiado exitosamente';
                break;
                
            case 'purge_varnish':
                // Limpiar solo Varnish
                if ( function_exists( 'snn_purge_varnish_urls' ) ) {
                    $purged = snn_purge_varnish_urls( [ home_url() ] );
                    $message = $purged > 0 ? '✅ Cache de Varnish limpiado exitosamente' : '⚠️ No se pudo limpiar Varnish';
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
            echo '<div class="notice notice-info"><p><strong>Resultados detallados:</strong></p><ul>';
            foreach ( $results as $system => $success ) {
                $icon = $success ? '✅' : '❌';
                echo '<li>' . $icon . ' ' . ucfirst( $system ) . '</li>';
            }
            echo '</ul></div>';
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
    
    // Obtener cache IDs activos
    $active_cache_ids = [];
    if ( function_exists( 'bl_get_cached_query_ids' ) ) {
        $active_cache_ids = bl_get_cached_query_ids();
    }
    
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
                                Cache IDs activos: <?php echo esc_html( implode( ', ', $active_cache_ids ) ); ?>
                            <?php else: ?>
                                No hay cache activo en este momento
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

