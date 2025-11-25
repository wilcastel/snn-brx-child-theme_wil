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
    static $storage = null;
    if ( $storage === null ) {
        $storage = [
            'queries' => [],
            'posts' => [],
        ];
    }
    return $storage;
}

/* Función para limpiar el caché de una consulta específica */
function bl_clear_cached_query( $cache_id = null ) {
    $storage = bl_get_cached_queries_storage();
    
    if ( $cache_id === null ) {
        // Limpiar todos los cachés
        $storage['queries'] = [];
        $storage['posts'] = [];
    } else {
        // Limpiar solo un caché específico
        unset( $storage['queries'][$cache_id] );
        unset( $storage['posts'][$cache_id] );
    }
}

/* Función para obtener todos los cache IDs activos */
function bl_get_cached_query_ids() {
    $storage = bl_get_cached_queries_storage();
    return array_keys( $storage['posts'] );
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
        
        // Procesar tax_query - Bricks usa formato especial: "taxonomy::term_id"
        if ( isset( $query_args['tax_query'] ) ) {
            $tax_query = $query_args['tax_query'];
            
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
            if ( is_array( $tax_query ) ) {
                $processed_tax_query = [];
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
                
                // Si procesamos algo, reemplazar el tax_query original
                if ( !empty( $processed_tax_query ) ) {
                    $query_args['tax_query'] = $processed_tax_query;
                }
            }
        }
        
        // Remover offset y paged de la query base (estos son solo para loops individuales)
        // PERO mantener posts_per_page si se especifica en la query base
        // Si posts_per_page NO está en la query base, usar -1 (todos los posts)
        unset( $query_args['offset'] );
        unset( $query_args['paged'] );
        
        // Si posts_per_page no está especificado en la query base, usar -1 (todos los posts)
        if ( !isset( $query_args['posts_per_page'] ) || empty( $query_args['posts_per_page'] ) ) {
            $query_args['posts_per_page'] = -1; // -1 = todos los posts disponibles
        }
        
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
    
    // Obtener el almacenamiento de caché
    $storage = bl_get_cached_queries_storage();
    
    // Si no existe el WP_Query en caché, crearlo y guardarlo
    if ( !isset( $storage['posts'][$cache_id] ) ) {
        // Crear el WP_Query completo con los argumentos especificados
        $wp_query = new WP_Query( $query_args );
        
        // Guardar el WP_Query completo en caché
        $storage['queries'][$cache_id] = $wp_query;
        
        // Guardar también los posts directamente para acceso más rápido
        $storage['posts'][$cache_id] = $wp_query->posts;
    }
    
    // Obtener todos los posts del caché (usar el array directo en lugar del WP_Query)
    $all_posts = $storage['posts'][$cache_id];
    
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

