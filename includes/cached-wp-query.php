<?php
/**
 * Bricks Builder - Cached WP Query System
 * ======================================
 * 
 * Sistema de caché para consultas WP_Query en Bricks Builder
 * 
 * Permite:
 * - Cachear una consulta grande (ej: 100 posts)
 * - Reutilizar el caché en múltiples loops con diferentes offsets/límites
 * - Una sola consulta MySQL para todos los loops
 * - Limpieza automática del caché cuando se publica/actualiza contenido
 * - Soporte para Custom Post Types, categorías, etiquetas, tax_query, etc.
 * 
 * @package SNN Theme
 * @since 1.0.0
 */

/**
 * OPTIMIZACIÓN: Cachear attachment_url_to_postid para evitar queries repetitivas
 * Esta función se llama muchas veces durante el renderizado, generando queries innecesarias
 * 
 * Estrategia: Usar dos filtros con diferentes prioridades
 * 1. Prioridad baja (1): Interceptar ANTES y verificar nuestro cache estático
 * 2. Prioridad alta (999): Cachear el resultado DESPUÉS de que WordPress lo obtiene
 */
add_filter('attachment_url_to_postid', function($post_id, $url) {
    // Cache estático para esta petición
    static $cache = [];
    static $cache_hits = 0;
    static $cache_misses = 0;
    
    // Si ya está en nuestro cache estático, devolverlo inmediatamente (evita la query)
    if (isset($cache[$url])) {
        $cache_hits++;
        if (defined('WP_DEBUG') && WP_DEBUG && defined('BL_CACHE_DEBUG') && BL_CACHE_DEBUG) {
            if (class_exists('WPPA_Cache_Query_Logger')) {
                WPPA_Cache_Query_Logger::log_attachment_cache('HIT', $url, $cache[$url], $cache_hits);
            } else {
                error_log(sprintf('ATTACHMENT_CACHE: HIT para URL "%s" - Post ID: %s (Total hits: %d)', 
                    basename($url), 
                    $cache[$url] ? $cache[$url] : 'false',
                    $cache_hits
                ));
            }
        }
        return $cache[$url];
    }
    
    // Si WordPress ya encontró un resultado (del cache de WordPress), guardarlo en nuestro cache
    if ($post_id) {
        $cache[$url] = $post_id;
        if (defined('WP_DEBUG') && WP_DEBUG && defined('BL_CACHE_DEBUG') && BL_CACHE_DEBUG) {
            if (class_exists('WPPA_Cache_Query_Logger')) {
                WPPA_Cache_Query_Logger::log_attachment_cache('SAVED', $url, $post_id);
            } else {
                error_log(sprintf('ATTACHMENT_CACHE: Guardado en cache desde WordPress - URL "%s" - Post ID: %s', 
                    basename($url), 
                    $post_id
                ));
            }
        }
        return $post_id;
    }
    
    // Si no hay resultado, NO guardar false todavía porque WordPress aún no ha hecho la query
    // Dejar que WordPress procese normalmente
    $cache_misses++;
    if (defined('WP_DEBUG') && WP_DEBUG && defined('BL_CACHE_DEBUG') && BL_CACHE_DEBUG) {
        if (class_exists('WPPA_Cache_Query_Logger')) {
            WPPA_Cache_Query_Logger::log_attachment_cache('MISS', $url, null, $cache_misses);
        } else {
            error_log(sprintf('ATTACHMENT_CACHE: MISS para URL "%s" - WordPress procesará (Total misses: %d)', 
                basename($url), 
                $cache_misses
            ));
        }
    }
    return false;
}, 1, 2); // Prioridad 1 para interceptar ANTES

// Segundo filtro para cachear el resultado DESPUÉS de que WordPress lo obtiene
add_filter('attachment_url_to_postid', function($post_id, $url) {
    static $cache = [];
    
    // Guardar el resultado en nuestro cache estático (incluso si es false)
    // Esto evita queries repetitivas en la misma petición
    if (!isset($cache[$url])) {
        $cache[$url] = $post_id;
        if (defined('WP_DEBUG') && WP_DEBUG && defined('BL_CACHE_DEBUG') && BL_CACHE_DEBUG) {
            if (class_exists('WPPA_Cache_Query_Logger')) {
                WPPA_Cache_Query_Logger::log_attachment_cache('CACHED_AFTER', $url, $post_id);
            } else {
                error_log(sprintf('ATTACHMENT_CACHE: Resultado cacheado DESPUÉS - URL "%s" - Post ID: %s', 
                    basename($url), 
                    $post_id ? $post_id : 'false'
                ));
            }
        }
    }
    
    return $post_id;
}, 999, 2); // Prioridad 999 para ejecutarse DESPUÉS y cachear el resultado final

/**
 * OPTIMIZACIÓN: Interceptar get_the_terms() para verificar si está usando el cache
 * Esto nos ayuda a identificar si las queries de términos se están generando durante el renderizado
 */
add_filter('get_the_terms', function($terms, $post_id, $taxonomy) {
    if (defined('WP_DEBUG') && WP_DEBUG && defined('BL_CACHE_DEBUG') && BL_CACHE_DEBUG) {
        global $wpdb;
        static $logged_calls = [];
        $call_key = $post_id . '_' . $taxonomy;
        
        // Solo loggear la primera llamada para cada post_id + taxonomía para evitar spam
        if (!isset($logged_calls[$call_key])) {
            $queries_before = $wpdb ? $wpdb->num_queries : 0;
            $logged_calls[$call_key] = true;
            
            // Verificar si los términos están en cache
            $cache_key = "{$taxonomy}_relationships";
            $cached = wp_cache_get($post_id, $cache_key);
            
            if ($cached === false) {
                error_log(sprintf('TERMS_CACHE_WARNING: get_the_terms() llamado para post_id %d, taxonomía "%s" - Cache NO encontrado (puede generar query)', 
                    $post_id, 
                    $taxonomy
                ));
            }
        }
    }
    
    return $terms;
}, 1, 3);

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
// Usar una variable global para poder acceder desde fuera de las funciones
function bl_get_cached_queries_storage() {
    global $bl_cached_queries_storage;
    if ( !isset( $bl_cached_queries_storage ) ) {
        $bl_cached_queries_storage = [
            'queries' => [],
            'posts' => [],      // Almacena los objetos WP_Post completos
            'post_ids' => [],   // Almacena solo los IDs para referencia rápida
        ];
    }
    return $bl_cached_queries_storage;
}

/* Función para limpiar el caché de una consulta específica */
function bl_clear_cached_query( $cache_id = null ) {
    global $bl_cached_queries_storage;
    
    if ( !isset( $bl_cached_queries_storage ) ) {
        return; // No hay nada que limpiar
    }
    
    if ( $cache_id === null ) {
        // Limpiar todos los cachés
        $bl_cached_queries_storage['queries'] = [];
        $bl_cached_queries_storage['posts'] = [];
        $bl_cached_queries_storage['post_ids'] = [];
    } else {
        // Limpiar solo un caché específico
        unset( $bl_cached_queries_storage['queries'][$cache_id] );
        unset( $bl_cached_queries_storage['posts'][$cache_id] );
        unset( $bl_cached_queries_storage['post_ids'][$cache_id] );
    }
}

/* Función para obtener todos los cache IDs activos */
function bl_get_cached_query_ids() {
    $storage = bl_get_cached_queries_storage();
    return array_keys( $storage['posts'] );
}

/* Función para obtener los IDs de posts de un caché específico */
function bl_get_cached_post_ids( $cache_id ) {
    global $bl_cached_queries_storage;
    if ( isset( $bl_cached_queries_storage['post_ids'][$cache_id] ) ) {
        return $bl_cached_queries_storage['post_ids'][$cache_id];
    }
    return [];
}

/* Función helper para filtrar posts por tax_query */
function bl_filter_posts_by_tax_query( $posts, $tax_query ) {
    if ( empty( $tax_query ) || empty( $posts ) ) {
        return $posts;
    }
    
    // Procesar tax_query - Bricks usa formato especial: "taxonomy::term_id"
    $processed_tax_query = [];
    
    // Si es string, intentar parsear
    if ( is_string( $tax_query ) ) {
        $tax_query_parsed = maybe_unserialize( $tax_query );
        if ( is_array( $tax_query_parsed ) ) {
            $tax_query = $tax_query_parsed;
        } else {
            $tax_query_json = json_decode( $tax_query, true );
            if ( is_array( $tax_query_json ) ) {
                $tax_query = $tax_query_json;
            }
        }
    }
    
    // Si es array, procesar cada elemento
    if ( !is_array( $tax_query ) ) {
        return $posts;
    }
    
    $taxonomy_groups = []; // Agrupar términos por taxonomía
    
    foreach ( $tax_query as $index => $tax_item ) {
        $taxonomy = null;
        $term_id = null;
        
        // Si el elemento es un string en formato "taxonomy::term_id" (formato de Bricks)
        if ( is_string( $tax_item ) && strpos( $tax_item, '::' ) !== false ) {
            list( $taxonomy, $term_id ) = explode( '::', $tax_item, 2 );
        }
        // Si el elemento ya es un array (formato estándar de WP_Query)
        elseif ( is_array( $tax_item ) ) {
            // Verificar si tiene la estructura correcta de WP_Query
            if ( isset( $tax_item['taxonomy'] ) && isset( $tax_item['terms'] ) ) {
                // Ya está en formato correcto, agregarlo directamente
                $processed_tax_query[] = $tax_item;
                continue;
            }
            // Si es un array anidado incorrecto (como [0] => ['post_tag::7'])
            elseif ( isset( $tax_item[0] ) && is_string( $tax_item[0] ) && strpos( $tax_item[0], '::' ) !== false ) {
                list( $taxonomy, $term_id ) = explode( '::', $tax_item[0], 2 );
            }
        }
        
        // Si tenemos taxonomía y término, agregarlo al grupo
        if ( $taxonomy && $term_id ) {
            if ( !isset( $taxonomy_groups[$taxonomy] ) ) {
                $taxonomy_groups[$taxonomy] = [];
            }
            $taxonomy_groups[$taxonomy][] = intval( $term_id );
        }
    }
    
    // Convertir los grupos en formato WP_Query
    foreach ( $taxonomy_groups as $taxonomy => $term_ids ) {
        $processed_tax_query[] = [
            'taxonomy' => $taxonomy,
            'field' => 'term_id',
            'terms' => array_unique( $term_ids ), // Eliminar duplicados
        ];
    }
    
    // Si no hay tax_query procesado, devolver todos los posts
    if ( empty( $processed_tax_query ) ) {
        return $posts;
    }
    
    // OPTIMIZACIÓN CRÍTICA: Los términos ya están precargados cuando se creó el caché
    // Solo necesitamos obtenerlos del cache de WordPress (sin queries adicionales)
    $post_ids = array_map( function( $post ) {
        return is_object( $post ) && isset( $post->ID ) ? $post->ID : ( is_numeric( $post ) ? $post : 0 );
    }, $posts );
    $post_ids = array_filter( $post_ids );
    
    // Obtener todas las taxonomías necesarias
    $taxonomies_needed = array_unique( array_column( $processed_tax_query, 'taxonomy' ) );
    
    // OPTIMIZACIÓN: Los términos ya deberían estar en cache desde la creación del caché
    // Pero si se están usando taxonomías diferentes o el cache no funcionó, precargar de nuevo
    // Esto es crítico porque get_the_terms() puede generar queries si el cache no está disponible
    if ( !empty( $post_ids ) && !empty( $taxonomies_needed ) ) {
        // Precargar términos para las taxonomías necesarias
        // update_object_term_cache() hace una sola query por taxonomía, no una query por post
        update_object_term_cache( $post_ids, 'post', $taxonomies_needed );
        
        // También precargar las taxonomías estándar de WordPress si no están en la lista
        $standard_taxonomies = ['category', 'post_tag', 'post_format'];
        $missing_taxonomies = array_diff( $standard_taxonomies, $taxonomies_needed );
        if ( !empty( $missing_taxonomies ) ) {
            // Precargar también las taxonomías estándar por si se usan durante el renderizado
            update_object_term_cache( $post_ids, 'post', $missing_taxonomies );
        }
    }
    
    // Obtener términos usando get_the_terms() que debería usar el cache precargado
    // Si el cache funciona correctamente, esto NO generará queries adicionales
    $all_post_terms = [];
    foreach ( $taxonomies_needed as $taxonomy ) {
        // Determinar qué field necesitamos según el tax_query
        $field = 'term_id';
        foreach ( $processed_tax_query as $tax_condition ) {
            if ( $tax_condition['taxonomy'] === $taxonomy ) {
                $field = isset( $tax_condition['field'] ) ? $tax_condition['field'] : 'term_id';
                break;
            }
        }
        
        $terms_by_post = [];
        foreach ( $post_ids as $post_id ) {
            // get_the_terms() debería usar el cache precargado (sin queries adicionales)
            // Si genera queries, el problema está en el cache de WordPress, no en nuestro código
            global $wpdb;
            $queries_before = $wpdb ? $wpdb->num_queries : 0;
            
            $terms = get_the_terms( $post_id, $taxonomy );
            
            $queries_after = $wpdb ? $wpdb->num_queries : 0;
            $queries_generated = $queries_after - $queries_before;
            
            if (defined('WP_DEBUG') && WP_DEBUG && defined('BL_CACHE_DEBUG') && BL_CACHE_DEBUG && $queries_generated > 0) {
                error_log(sprintf('TERMS_CACHE: ⚠️ Query generada para post_id %d, taxonomía "%s" - Queries: %d', 
                    $post_id, 
                    $taxonomy,
                    $queries_generated
                ));
            }
            
            if ( $terms && !is_wp_error( $terms ) ) {
                $terms_by_post[$post_id] = array_map( function( $term ) use ( $field ) {
                    return $field === 'term_id' ? $term->term_id : $term->slug;
                }, $terms );
            } else {
                $terms_by_post[$post_id] = [];
            }
        }
        $all_post_terms[$taxonomy] = $terms_by_post;
    }
    
    // Filtrar los posts según el tax_query usando los términos ya cargados
    $filtered_posts = [];
    foreach ( $posts as $post ) {
        $post_id = is_object( $post ) && isset( $post->ID ) ? $post->ID : ( is_numeric( $post ) ? $post : 0 );
        if ( !$post_id ) {
            continue;
        }
        
        $matches = true;
        
        foreach ( $processed_tax_query as $tax_condition ) {
            $taxonomy = $tax_condition['taxonomy'];
            $terms = is_array( $tax_condition['terms'] ) ? $tax_condition['terms'] : [ $tax_condition['terms'] ];
            $operator = isset( $tax_condition['operator'] ) ? $tax_condition['operator'] : 'IN';
            
            // Obtener los términos del post desde el caché (ya cargados)
            $post_terms = isset( $all_post_terms[$taxonomy][$post_id] ) 
                ? $all_post_terms[$taxonomy][$post_id] 
                : [];
            
            if ( $operator === 'IN' ) {
                // El post debe tener al menos uno de los términos
                $intersection = array_intersect( $terms, $post_terms );
                if ( empty( $intersection ) ) {
                    $matches = false;
                    break;
                }
            } elseif ( $operator === 'NOT IN' ) {
                // El post NO debe tener ninguno de los términos
                $intersection = array_intersect( $terms, $post_terms );
                if ( !empty( $intersection ) ) {
                    $matches = false;
                    break;
                }
            } elseif ( $operator === 'AND' ) {
                // El post debe tener TODOS los términos
                $intersection = array_intersect( $terms, $post_terms );
                if ( count( $intersection ) !== count( $terms ) ) {
                    $matches = false;
                    break;
                }
            }
        }
        
        if ( $matches ) {
            $filtered_posts[] = $post;
        }
    }
    
    return $filtered_posts;
}

/* Run new query if option selected - Prioridad alta para ejecutarse antes que otros filtros */
add_filter( 'bricks/query/run', 'bl_maybe_run_cached_query', 5, 2);
function bl_maybe_run_cached_query( $results, $query_obj ) {
    // Solo procesar si es nuestro tipo de query
    if ( $query_obj->object_type !== 'cached_wp_query' ) {
        return $results;
    }
    
    // Debug: Verificar si el sistema de caché se está ejecutando
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'BL_CACHE_DEBUG' ) && BL_CACHE_DEBUG ) {
        if (class_exists('WPPA_Cache_Query_Logger')) {
            WPPA_Cache_Query_Logger::log_cache_processing($query_obj->object_type);
        } else {
            error_log( 'BL_CACHE: Procesando cached_wp_query - object_type: ' . $query_obj->object_type );
        }
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
    
    // Leer tax_query del loop (si existe) - esto se aplicará DESPUÉS sobre los posts del caché
    $loop_tax_query = null;
    if ( isset( $settings['cached_wp_query_args']['tax_query'] ) && !empty( $settings['cached_wp_query_args']['tax_query'] ) ) {
        $loop_tax_query = $settings['cached_wp_query_args']['tax_query'];
    }
    
    // Obtener los argumentos de la consulta base
    // IMPORTANTE: Separar los argumentos de la query base de los parámetros del loop
    $query_args = [];
    if ( isset( $settings['cached_wp_query_args'] ) && !empty( $settings['cached_wp_query_args'] ) ) {
        // Hacer una copia de los argumentos para no modificar el original
        $query_args = is_array( $settings['cached_wp_query_args'] ) 
            ? $settings['cached_wp_query_args'] 
            : [];
        
        // Convertir formatos de Bricks a formatos de WP_Query si es necesario
        // Bricks puede usar 'category' en lugar de 'category__in'
        if ( isset( $query_args['category'] ) && !isset( $query_args['category__in'] ) ) {
            $category_value = $query_args['category'];
            
            // Si es un número o array de números, usar category__in
            if ( is_numeric( $category_value ) ) {
                $query_args['category__in'] = [ intval( $category_value ) ];
            } elseif ( is_array( $category_value ) ) {
                // Si es array, convertir todos a int
                $query_args['category__in'] = array_map( 'intval', array_filter( $category_value, 'is_numeric' ) );
            } elseif ( is_string( $category_value ) ) {
                // Si es string, puede ser un slug o ID
                $term = get_term_by( 'slug', $category_value, 'category' );
                if ( $term ) {
                    $query_args['category__in'] = [ $term->term_id ];
                } elseif ( is_numeric( $category_value ) ) {
                    $query_args['category__in'] = [ intval( $category_value ) ];
                }
            }
            unset( $query_args['category'] );
        }
        
        // Bricks puede usar 'tag' en lugar de 'tag__in'
        if ( isset( $query_args['tag'] ) && !isset( $query_args['tag__in'] ) ) {
            $tag_value = $query_args['tag'];
            
            // Si es un número o array de números, usar tag__in
            if ( is_numeric( $tag_value ) ) {
                $query_args['tag__in'] = [ intval( $tag_value ) ];
            } elseif ( is_array( $tag_value ) ) {
                // Si es array, convertir todos a int
                $query_args['tag__in'] = array_map( 'intval', array_filter( $tag_value, 'is_numeric' ) );
            } elseif ( is_string( $tag_value ) ) {
                // Si es string, puede ser un slug o ID
                $term = get_term_by( 'slug', $tag_value, 'post_tag' );
                if ( $term ) {
                    $query_args['tag__in'] = [ $term->term_id ];
                } elseif ( is_numeric( $tag_value ) ) {
                    $query_args['tag__in'] = [ intval( $tag_value ) ];
                }
            }
            unset( $query_args['tag'] );
        }
        
        // Manejar category__in y tag__in si vienen como strings o arrays mixtos
        if ( isset( $query_args['category__in'] ) ) {
            $cats = $query_args['category__in'];
            if ( is_string( $cats ) ) {
                // Si es string, intentar convertir a array
                $cats = explode( ',', $cats );
            }
            if ( is_array( $cats ) ) {
                $query_args['category__in'] = array_map( 'intval', array_filter( $cats, 'is_numeric' ) );
            }
        }
        
        if ( isset( $query_args['tag__in'] ) ) {
            $tags = $query_args['tag__in'];
            if ( is_string( $tags ) ) {
                // Si es string, intentar convertir a array
                $tags = explode( ',', $tags );
            }
            if ( is_array( $tags ) ) {
                $query_args['tag__in'] = array_map( 'intval', array_filter( $tags, 'is_numeric' ) );
            }
        }
        
        // IMPORTANTE: Remover tax_query, offset y paged de la query base
        // Estos parámetros son solo para loops individuales, NO para la query base
        // El tax_query se aplicará DESPUÉS sobre los posts del caché
        // NOTA: posts_per_page se maneja después, dependiendo de si es el loop master o no
        unset( $query_args['tax_query'] );
        unset( $query_args['offset'] );
        unset( $query_args['paged'] );
        
        // posts_per_page se manejará después, cuando sepamos si es el loop master o no
        
        $query_args['no_found_rows'] = false; // Necesario para que funcione correctamente
    } else {
        // Valores por defecto si no se especifica
        $query_args = [
            'post_type' => 'post',
            'posts_per_page' => -1, // Traer todos los posts por defecto
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => false,
        ];
    }
    
    // Obtener el almacenamiento de caché usando variable global directamente
    global $bl_cached_queries_storage;
    bl_get_cached_queries_storage(); // Asegurar que la variable global esté inicializada
    
    // Si no existe el WP_Query en caché, crearlo y guardarlo
    // IMPORTANTE: Solo el primer loop (master) crea el caché y puede definir posts_per_page
    if ( !isset( $bl_cached_queries_storage['posts'][$cache_id] ) ) {
        // Este es el loop master - puede definir posts_per_page para la query base
        // Si el loop master tiene posts_per_page en sus argumentos, usarlo
        // Si no, usar -1 (todos los posts)
        $master_posts_per_page = ( $posts_per_page !== null && $posts_per_page > 0 ) ? $posts_per_page : -1;
        
        // Aplicar posts_per_page del master a la query base
        $query_args['posts_per_page'] = $master_posts_per_page;
        
        // Crear el WP_Query completo con los argumentos especificados
        $wp_query = new WP_Query( $query_args );
        
        // Guardar el WP_Query completo en caché
        $bl_cached_queries_storage['queries'][$cache_id] = $wp_query;
        
        // Guardar los posts completos del WP_Query original
        // Esto evita hacer get_post() para cada post (que genera queries)
        // Usamos serialize/unserialize para crear copias independientes y evitar problemas de referencias
        $cached_posts = [];
        $post_ids = [];
        foreach ( $wp_query->posts as $post ) {
            // Serializar y deserializar para crear una copia independiente del objeto
            // Esto evita problemas de referencias y asegura que los posts sean independientes
            $cached_posts[] = unserialize( serialize( $post ) );
            $post_ids[] = $post->ID;
        }
        $bl_cached_queries_storage['posts'][$cache_id] = $cached_posts;
        $bl_cached_queries_storage['post_ids'][$cache_id] = $post_ids;
        
        // OPTIMIZACIÓN CRÍTICA: Precargar TODOS los datos necesarios de una vez
        // Esto se hace UNA SOLA VEZ cuando se crea el caché, no cada vez que se renderiza
        
        // 1. Precargar términos (categorías, etiquetas, taxonomías personalizadas)
        // Obtener todos los post types únicos en el caché
        $post_types_in_cache = [];
        foreach ( $cached_posts as $cached_post ) {
            if ( isset( $cached_post->post_type ) ) {
                $post_types_in_cache[$cached_post->post_type] = true;
            }
        }
        
        // Precargar términos para cada post type en el caché
        // Esto es crítico porque diferentes post types pueden tener diferentes taxonomías
        if ( !empty( $post_ids ) && !empty( $post_types_in_cache ) ) {
            foreach ( array_keys( $post_types_in_cache ) as $post_type ) {
                // Obtener todas las taxonomías de este post type (incluyendo personalizadas)
                $taxonomies_for_type = get_object_taxonomies( $post_type, 'names' );
                
                if ( !empty( $taxonomies_for_type ) ) {
                    // Obtener los IDs de posts de este tipo específico
                    $post_ids_for_type = [];
                    foreach ( $cached_posts as $cached_post ) {
                        if ( isset( $cached_post->post_type ) && $cached_post->post_type === $post_type ) {
                            $post_ids_for_type[] = $cached_post->ID;
                        }
                    }
                    
                    if ( !empty( $post_ids_for_type ) && !empty( $taxonomies_for_type ) ) {
                        // Precargar todos los términos de este post type en el cache de WordPress
                        // Esto hace una sola query por taxonomía, no una query por post
                        update_object_term_cache( $post_ids_for_type, $post_type, $taxonomies_for_type );
                        
                        if ( defined('WP_DEBUG') && WP_DEBUG && defined('BL_CACHE_DEBUG') && BL_CACHE_DEBUG ) {
                            if (class_exists('WPPA_Cache_Query_Logger')) {
                                WPPA_Cache_Query_Logger::log_terms_precache($post_type, count($post_ids_for_type), $taxonomies_for_type);
                            } else {
                                error_log(sprintf('TERMS_PRECACHE: Precargados términos para post_type "%s" - Posts: %d, Taxonomías: %s', 
                                    $post_type,
                                    count($post_ids_for_type),
                                    implode(', ', $taxonomies_for_type)
                                ));
                            }
                        }
                    }
                }
            }
        }
        
        // 2. Precargar meta fields (incluyendo _thumbnail_id para imágenes destacadas)
        // Esto evita queries cuando se llama get_post_meta() o get_post_thumbnail_id()
        if ( !empty( $post_ids ) ) {
            // Precargar todos los meta fields de todos los posts de una vez
            // Esto hace una sola query para todos los meta fields, no una query por post
            update_postmeta_cache( $post_ids );
            
            // 3. Precargar imágenes destacadas (featured images)
            // Obtener todos los IDs de imágenes destacadas de una vez usando el cache precargado
            $thumbnail_ids = [];
            foreach ( $post_ids as $post_id ) {
                // get_post_thumbnail_id() ahora usará el cache precargado (sin queries adicionales)
                $thumbnail_id = get_post_thumbnail_id( $post_id );
                if ( $thumbnail_id ) {
                    $thumbnail_ids[] = $thumbnail_id;
                }
            }
            
            // Precargar metadata de attachments (imágenes destacadas)
            if ( !empty( $thumbnail_ids ) ) {
                // Precargar meta fields de los attachments
                update_postmeta_cache( $thumbnail_ids );
                // Precargar los objetos de attachment en memoria
                _prime_post_caches( $thumbnail_ids, false, true );
                
                // OPTIMIZACIÓN CRÍTICA: Precargar wp_get_attachment_metadata() para todas las imágenes
                // Esto evita queries cuando Bricks llama a wp_get_attachment_image_src()
                // wp_get_attachment_metadata() se cachea automáticamente en wp_cache, pero necesitamos precargarlo
                foreach ( $thumbnail_ids as $thumb_id ) {
                    // Llamar a wp_get_attachment_metadata() para precargarlo en cache
                    // Esto carga los metadatos de la imagen (tamaños, dimensiones, etc.)
                    wp_get_attachment_metadata( $thumb_id );
                }
                
                if ( defined('WP_DEBUG') && WP_DEBUG && defined('BL_CACHE_DEBUG') && BL_CACHE_DEBUG ) {
                    if (class_exists('WPPA_Cache_Query_Logger')) {
                        WPPA_Cache_Query_Logger::log_thumbnails_precache(count($thumbnail_ids));
                    } else {
                        error_log(sprintf('THUMBNAILS_PRECACHE: Precargadas %d imágenes destacadas (incluyendo metadata)', count($thumbnail_ids)));
                    }
                }
            }
            
            // 4. Precargar autores de los posts (evita queries cuando se llama get_the_author())
            // NOTA: Solo precargamos objetos de usuario, no meta fields, ya que no se usan en las consultas
            $author_ids = [];
            foreach ( $cached_posts as $cached_post ) {
                if ( isset( $cached_post->post_author ) && $cached_post->post_author ) {
                    $author_ids[] = (int) $cached_post->post_author;
                }
            }
            $author_ids = array_unique( $author_ids );
            if ( !empty( $author_ids ) ) {
                // Precargar objetos de usuario en memoria
                // get_userdata() automáticamente cachea el resultado y es suficiente
                // No precargamos user meta porque no se usa en las consultas y causaba warnings
                foreach ( $author_ids as $user_id ) {
                    // get_userdata() cachea automáticamente el resultado
                    get_userdata( $user_id );
                }
                
                if ( defined('WP_DEBUG') && WP_DEBUG && defined('BL_CACHE_DEBUG') && BL_CACHE_DEBUG ) {
                    if (class_exists('WPPA_Cache_Query_Logger')) {
                        WPPA_Cache_Query_Logger::log_authors_precache(count($author_ids));
                    } else {
                        error_log(sprintf('AUTHORS_PRECACHE: Precargados %d autores (solo objetos, sin meta)', count($author_ids)));
                    }
                }
            }
        }
    }
    
    
    // Obtener los posts completos del caché (ya están cargados, no necesitamos get_post())
    $all_cached_posts = isset( $bl_cached_queries_storage['posts'][$cache_id] ) 
        ? $bl_cached_queries_storage['posts'][$cache_id] 
        : [];
    
    // Si no hay posts, devolver array vacío
    if ( empty( $all_cached_posts ) ) {
        return [];
    }
    
    // Aplicar filtro de tax_query del loop (si existe) sobre los posts del caché
    if ( $loop_tax_query !== null ) {
        $all_cached_posts = bl_filter_posts_by_tax_query( $all_cached_posts, $loop_tax_query );
    }
    
    $total_posts = count( $all_cached_posts );
    
    // Validar offset
    if ( $offset >= $total_posts ) {
        return [];
    }
    
    // Calcular la longitud a extraer
    $length = null; // null = hasta el final
    if ( $posts_per_page !== null && $posts_per_page > 0 ) {
        // Calcular cuántos posts quedan después del offset
        $remaining = $total_posts - $offset;
        // Tomar el mínimo entre lo que se pide y lo que queda
        $length = min( $posts_per_page, $remaining );
    }
    
    // Aplicar offset y posts_per_page sobre los posts filtrados
    $all_posts = array_slice( $all_cached_posts, $offset, $length );
    
    // Reindexar para asegurar índices consecutivos (0, 1, 2, ...)
    $all_posts = array_values( $all_posts );
    
    // Devolver nuestros resultados filtrados, ignorando cualquier resultado que Bricks haya generado
    // Esto asegura que cada loop use el caché compartido con sus propios filtros
    
    // Debug: Log cuando se usa el caché
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'BL_CACHE_DEBUG' ) && BL_CACHE_DEBUG ) {
        global $wpdb;
        $queries_so_far = $wpdb ? $wpdb->num_queries : 0;
        $has_tax_query = $loop_tax_query !== null ? 'Sí' : 'No';
        $offset_info = $offset > 0 ? "offset: $offset" : 'sin offset';
        $ppp_info = $posts_per_page !== null ? "ppp: $posts_per_page" : 'sin límite';
        
        // Calcular queries generadas en este uso del caché
        static $last_query_count = 0;
        $queries_in_this_call = $queries_so_far - $last_query_count;
        
        // Verificar si este es el loop master (crea el cache) o un loop que reutiliza el cache
        global $bl_cached_queries_storage;
        $is_master_loop = !isset( $bl_cached_queries_storage['posts'][$cache_id] );
        
        $last_query_count = $queries_so_far;
        
        // Log usando el logger del plugin si está disponible
        if (class_exists('WPPA_Cache_Query_Logger')) {
            WPPA_Cache_Query_Logger::log_cache_usage(
                $cache_id,
                count($all_posts),
                $queries_so_far,
                $queries_in_this_call,
                $has_tax_query,
                $offset_info,
                $ppp_info,
                $is_master_loop
            );
        } else {
            // Fallback a error_log si el plugin no está disponible
            if ( !$is_master_loop && $queries_in_this_call > 5 && defined('WP_DEBUG') && WP_DEBUG && defined('BL_CACHE_DEBUG') && BL_CACHE_DEBUG ) {
                error_log( sprintf( 
                    '⚠️ BL_CACHE: Cache ID "%s" generó %d queries durante renderizado (Posts: %d) - Esto puede indicar funciones de WordPress que no están usando el cache', 
                    $cache_id, 
                    $queries_in_this_call,
                    count( $all_posts )
                ) );
            }
            
            error_log( sprintf( 
                'BL_CACHE: Cache ID "%s" - Posts: %d, Queries totales: %d (+%d en este call), Tax_query: %s, %s, %s', 
                $cache_id, 
                count( $all_posts ),
                $queries_so_far,
                $queries_in_this_call,
                $has_tax_query,
                $offset_info,
                $ppp_info
            ) );
        }
    }
    
    return $all_posts;
}

/* Setup post data for posts */
add_filter( 'bricks/query/loop_object', 'bl_setup_cached_post_data', 10, 3);
function bl_setup_cached_post_data( $loop_object, $loop_key, $query_obj ) {
    if ( $query_obj->object_type !== 'cached_wp_query' ) {
        return $loop_object;
    }
    
    global $post;
    
    // OPTIMIZACIÓN: $loop_object ya debería ser un objeto post completo del caché
    // NO hacer get_post() que genera queries
    if ( is_object( $loop_object ) && isset( $loop_object->ID ) ) {
        // Ya es un objeto post, usarlo directamente
        $post = $loop_object;
    } elseif ( is_numeric( $loop_object ) ) {
        // Si es solo un ID (no debería pasar, pero por seguridad)
        // Intentar obtener el post desde el caché primero antes de hacer get_post()
        global $bl_cached_queries_storage;
        $found_in_cache = false;
        
        if ( isset( $bl_cached_queries_storage['posts'] ) ) {
            foreach ( $bl_cached_queries_storage['posts'] as $cached_posts ) {
                foreach ( $cached_posts as $cached_post ) {
                    if ( is_object( $cached_post ) && isset( $cached_post->ID ) && $cached_post->ID == $loop_object ) {
                        $post = $cached_post;
                        $found_in_cache = true;
                        break 2;
                    }
                }
            }
        }
        
        // Solo hacer get_post() si no se encontró en el caché (no debería pasar)
        if ( !$found_in_cache ) {
            $post = get_post( $loop_object );
        }
    } else {
        // No es ni objeto ni ID válido
        return $loop_object;
    }
    
    if ( $post ) {
        setup_postdata( $post );
    }
    
    return $loop_object;
}

/****************** 
 * Limpiar caché automáticamente cuando se publica/actualiza/elimina un post
 ******************/

/* Limpiar todos los cachés cuando se guarda un post (publicar o actualizar) */
add_action( 'save_post', 'bl_clear_cached_queries_on_post_save', 10, 2 );
function bl_clear_cached_queries_on_post_save( $post_id, $post ) {
    // Evitar limpiar en autosaves y revisiones
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    
    // Limpiar todos los cachés cuando se guarda cualquier post
    bl_clear_cached_query();
}

/* Limpiar todos los cachés cuando se elimina un post */
add_action( 'delete_post', 'bl_clear_cached_queries_on_post_delete', 10, 1 );
function bl_clear_cached_queries_on_post_delete( $post_id ) {
    bl_clear_cached_query();
}

// ============================================
// LOGGING DETALLADO DE QUERIES DURANTE RENDERIZADO
// ============================================
// NOTA: El logging detallado ahora se maneja en el plugin WP Performance Auditor
// Si el plugin está activo, usa sus funciones. Si no, el logging se desactiva.
// Esto permite mantener el theme limpio y centralizar el logging en el plugin.
if ( defined('WP_DEBUG') && WP_DEBUG && defined('BL_CACHE_DEBUG') && BL_CACHE_DEBUG ) {
    // El plugin WPPA_Cache_Query_Logger se encarga de todo el logging
    // Solo necesitamos asegurarnos de que esté cargado
    if (!class_exists('WPPA_Cache_Query_Logger')) {
        // Si el plugin no está disponible, no hacemos logging detallado
        // El sistema de cache sigue funcionando, solo sin logging
    }
}

/* Limpiar todos los cachés cuando cambia el estado de un post (publicado, borrador, etc.) */
add_action( 'transition_post_status', 'bl_clear_cached_queries_on_status_change', 10, 3 );
function bl_clear_cached_queries_on_status_change( $new_status, $old_status, $post ) {
    // Limpiar si cambia a/desde 'publish' para cualquier tipo de post (incluyendo CPTs)
    if ( $new_status === 'publish' || $old_status === 'publish' ) {
        bl_clear_cached_query();
    }
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
            'description' => esc_html__( 'Argumentos de WP_Query para la consulta base. Puedes configurar: post_type, posts_per_page (opcional, por defecto trae todos), categorías, etiquetas, tax_query, meta_query, orderby, order, etc. Los loops individuales pueden usar Offset y Posts per Page para filtrar estos resultados.', 'bricks' )
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

