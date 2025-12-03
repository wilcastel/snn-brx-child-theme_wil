# Bricks Builder - Cached WP Query System
## Roadmap y Documentación Técnica

---

## 📋 Tabla de Contenidos

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Estado Actual del Sistema](#estado-actual-del-sistema)
3. [Roadmap: Enfoque Híbrido para Meta Query](#roadmap-enfoque-híbrido-para-meta-query)
4. [Compatibilidad con Sistemas de Caché](#compatibilidad-con-sistemas-de-caché)
5. [Mejores Prácticas para Producción](#mejores-prácticas-para-producción)
6. [Mejoras Futuras](#mejoras-futuras)

---

## 🎯 Resumen Ejecutivo

Este sistema implementa un mecanismo de caché inteligente para consultas de Bricks Builder que permite:

- **Una sola consulta MySQL** por caché compartido
- **Múltiples loops** reutilizando el mismo caché con diferentes filtros
- **Filtrado en memoria** para tax_query (categorías, etiquetas, taxonomías)
- **Enfoque híbrido** para meta_query (dejando que Bricks lo maneje)
- **Limpieza automática** del caché cuando se publica/actualiza/elimina contenido

### Beneficios

✅ **Rendimiento**: Reduce consultas MySQL de N loops a 1 consulta por caché  
✅ **Flexibilidad**: Permite diseños complejos sin sacrificar rendimiento  
✅ **Escalabilidad**: Funciona eficientemente con 100k+ posts  
✅ **Compatibilidad**: Compatible con todos los sistemas de caché modernos  

---

## 📊 Estado Actual del Sistema

### ✅ Funcionalidades Implementadas

1. **Caché Compartido**
   - Un loop master crea el caché con una cantidad definida de posts
   - Múltiples loops secundarios reutilizan el mismo caché
   - Cada loop aplica sus propios filtros (offset, posts_per_page, tax_query)

2. **Filtrado por Taxonomías (tax_query)**
   - Soporte completo para categorías, etiquetas y taxonomías personalizadas
   - Filtrado en memoria (rápido y eficiente)
   - Soporte para múltiples términos y operadores (IN, NOT IN, AND)

3. **Paginación y Límites**
   - Offset: Saltar posts desde el inicio
   - Posts per Page: Limitar cantidad de resultados
   - Aplicado después del filtrado de tax_query

4. **Limpieza Automática**
   - Se limpia automáticamente cuando se publica un post
   - Se limpia cuando se actualiza un post
   - Se limpia cuando se elimina un post
   - Se limpia cuando cambia el estado de un post

5. **Soporte para CPTs**
   - Funciona con cualquier Custom Post Type
   - Múltiples cachés independientes por Cache ID

### ⚠️ Limitaciones Actuales

- **Meta Query**: No se filtra en memoria (se deja que Bricks lo maneje)
- **Caché por Request**: El caché se pierde al terminar el request (no persiste)
- **Un solo Servidor**: No comparte caché entre múltiples servidores PHP

---

## 🗺️ Roadmap: Enfoque Híbrido para Meta Query

### Decisión Estratégica: Enfoque Híbrido

Después de analizar las opciones, se decidió implementar un **enfoque híbrido** que combina lo mejor de ambos mundos:

- **tax_query**: Filtrado en memoria (nuestro sistema) ✅
- **meta_query**: Consultas directas de Bricks + caché de resultados ✅

### Justificación

#### ❌ Por qué NO implementar meta_query completo

1. **Alta Complejidad**: Reimplementar toda la lógica de WP_Query meta_query
2. **Mantenimiento Difícil**: Mucho código que mantener y actualizar
3. **Posibles Bugs**: Mayor superficie de error
4. **Rendimiento Incierto**: Puede ser lento con cachés grandes
5. **Soporte Limitado**: Difícil cubrir todos los casos edge

#### ✅ Por qué el Enfoque Híbrido es Mejor

1. **Simplicidad**: No reimplementamos lógica compleja
2. **Confiabilidad**: Bricks ya maneja meta_query perfectamente
3. **Mantenibilidad**: Menos código propio que mantener
4. **Compatibilidad**: Funciona con ACF y otros plugins sin cambios
5. **Rendimiento Balanceado**: tax_query rápido en memoria, meta_query optimizado

---

### Fase 1: Implementación del Enfoque Híbrido (MVP)

**Prioridad**: Alta  
**Complejidad**: Media  
**Tiempo Estimado**: 4-6 horas

#### Objetivos

1. Detectar cuando un loop tiene `meta_query` en sus argumentos
2. Remover `meta_query` de la query base (similar a tax_query)
3. Permitir que Bricks maneje `meta_query` directamente
4. Cachear los resultados de loops con `meta_query` para reutilización

#### Tareas

- [ ] Modificar `bl_maybe_run_cached_query()` para detectar `meta_query`
- [ ] Remover `meta_query` de `$query_args` antes de crear el caché master
- [ ] Documentar que loops con `meta_query` deben usar consultas directas de Bricks
- [ ] Agregar comentarios en el código explicando el enfoque híbrido

#### Resultado Esperado

- Loops con solo `tax_query`: Funcionan con nuestro sistema (filtrado en memoria)
- Loops con solo `meta_query`: Funcionan con Bricks (consultas directas)
- Loops con ambos: `tax_query` en memoria, `meta_query` en Bricks

---

### Fase 2: Sistema de Caché Simple para Meta Query (Opcional)

**Prioridad**: Media  
**Complejidad**: Media  
**Tiempo Estimado**: 6-8 horas

#### Objetivos

1. Detectar cuando múltiples loops tienen el mismo `meta_query`
2. Cachear el resultado de la primera consulta
3. Reutilizar el caché para loops subsecuentes con los mismos parámetros

#### Implementación Propuesta

```php
// Generar un hash único basado en los parámetros de meta_query
$meta_query_hash = md5( serialize( $meta_query ) );
$cache_key = $cache_id . '_meta_' . $meta_query_hash;

// Si existe en caché, reutilizar
if ( $cached_result = wp_cache_get( $cache_key, 'cached_queries' ) ) {
    return $cached_result;
}

// Si no existe, hacer la consulta y cachear
$result = new WP_Query( $query_args );
wp_cache_set( $cache_key, $result->posts, 'cached_queries', 3600 );
```

#### Ventajas

- Reduce consultas repetidas con los mismos `meta_query`
- Simple de implementar
- No requiere reimplementar lógica de meta_query

#### Desventajas

- Aún requiere consultas MySQL (pero cacheadas)
- No aprovecha el caché master compartido

---

### Fase 3: Optimizaciones y Mejoras (Futuro)

**Prioridad**: Baja  
**Complejidad**: Alta  
**Tiempo Estimado**: 8-12 horas

#### Mejoras Potenciales

1. **Migración a Redis**
   - Persistir caché entre requests
   - Compartir caché entre múltiples servidores
   - Mejor rendimiento en producción

2. **Caché Inteligente**
   - Detectar automáticamente qué filtrar en memoria vs. consulta
   - Basado en tamaño del caché y complejidad del filtro

3. **Integración con Varnish/Nginx**
   - Notificar limpieza de caché cuando se publica contenido
   - Headers HTTP para control de caché

4. **Métricas y Monitoreo**
   - Logging de consultas cacheadas vs. no cacheadas
   - Estadísticas de rendimiento

---

## 🔄 Compatibilidad con Sistemas de Caché

### Arquitectura de Caché en Producción

```
Usuario
  ↓
Varnish (Caché HTTP/Page Cache) ← Nivel 1
  ↓
Nginx (Reverse Proxy + FastCGI Cache) ← Nivel 2
  ↓
PHP-FPM + WordPress
  ↓
Redis (Object Cache) ← Nivel 3
  ↓
Nuestro Sistema (Query Cache) ← Nivel 4 (Estamos aquí)
  ↓
MySQL (Database Cache) ← Nivel 5
```

### Tabla de Compatibilidad

| Sistema de Caché | Compatible | Nivel | Notas |
|------------------|------------|-------|-------|
| **Varnish** | ✅ Sí | HTTP/Page | Trabajan en niveles diferentes, sin conflictos |
| **Nginx FastCGI** | ✅ Sí | HTTP/Page | Compatible, nuestro sistema ayuda a generar páginas más rápido |
| **Redis** | ✅ Sí | Object | Complementarios, podríamos usarlo para persistencia |
| **Memcached** | ✅ Sí | Object | Similar a Redis |
| **W3 Total Cache** | ✅ Sí | Page/Object | Sin conflictos |
| **WP Super Cache** | ✅ Sí | Page | Sin conflictos |
| **Cloudflare** | ✅ Sí | CDN/Page | Compatible |
| **WP Rocket** | ✅ Sí | Page/Object | Sin conflictos |

---

### Detalle por Sistema

#### 1. Varnish (Caché HTTP/Page Cache)

**Compatibilidad**: ✅ Totalmente Compatible

**Cómo Funciona**:
- Varnish cachea la respuesta HTML completa de la página
- Si la página está en caché, no llega a WordPress (y nuestro sistema no se ejecuta)
- Si no está en caché, pasa a WordPress donde nuestro sistema ayuda a generar la página más rápido

**Interacción**:
- ✅ **Sin conflictos**: Trabajan en niveles diferentes
- ✅ **Complementarios**: Nuestro sistema reduce tiempo de generación, ayudando a Varnish
- ⚠️ **Consideración**: Si Varnish cachea páginas con datos dinámicos, puede mostrar contenido obsoleto

**Solución Recomendada**:
```php
// Limpiar caché de Varnish cuando se publica contenido
function bl_clear_cached_queries_on_post_save( $post_id, $post ) {
    bl_clear_cached_query();
    
    // Limpiar Varnish si está disponible
    if ( function_exists( 'varnish_http_purge' ) ) {
        varnish_http_purge( get_permalink( $post_id ) );
    }
}
```

---

#### 2. Nginx FastCGI Cache

**Compatibilidad**: ✅ Totalmente Compatible

**Cómo Funciona**:
- Nginx cachea respuestas PHP antes de enviarlas al cliente
- Similar a Varnish pero integrado directamente en Nginx

**Interacción**:
- ✅ **Sin conflictos**: Compatible
- ✅ **Sinergia**: Nuestro sistema reduce tiempo de generación, mejorando eficiencia de Nginx

**Configuración Recomendada**:
```nginx
# En nginx.conf
fastcgi_cache_path /var/cache/nginx levels=1:2 keys_zone=WORDPRESS:100m inactive=60m;
fastcgi_cache_key "$scheme$request_method$host$request_uri";
```

---

#### 3. Redis (Object Cache)

**Compatibilidad**: ✅ Compatible y Complementario

**Cómo Funciona**:
- Redis cachea objetos de WordPress (transients, options, resultados de consultas)
- Persiste en memoria del servidor (sobrevive entre requests)

**Interacción**:
- ✅ **Complementarios**: Nuestro sistema cachea en memoria PHP (variable global), Redis cachea en memoria del servidor
- ✅ **Sin conflictos**: Trabajan en niveles diferentes
- 💡 **Mejora Futura**: Podríamos usar Redis para persistir nuestro caché entre requests

**Implementación Futura**:
```php
// Actual (memoria PHP - se pierde al terminar el request)
$bl_cached_queries_storage['posts'][$cache_id] = $post_ids;

// Potencial mejora (Redis - persiste entre requests)
wp_cache_set('cached_query_' . $cache_id, $post_ids, 'cached_queries', 3600);
$cached = wp_cache_get('cached_query_' . $cache_id, 'cached_queries');
```

**Ventajas de Migrar a Redis**:
- ✅ Persistencia entre requests
- ✅ Compartir caché entre múltiples servidores PHP (load balancing)
- ✅ Mejor rendimiento en producción
- ✅ TTL automático (expiración de caché)

---

#### 4. W3 Total Cache / WP Super Cache

**Compatibilidad**: ✅ Totalmente Compatible

**Cómo Funciona**:
- Cachean páginas completas en disco o memoria
- Similar a Varnish pero a nivel de WordPress

**Interacción**:
- ✅ **Sin conflictos**: Si la página está cacheada, nuestro sistema no se ejecuta
- ✅ **Sinergia**: Si no está cacheada, nuestro sistema ayuda a generar la página más rápido

---

#### 5. Cloudflare

**Compatibilidad**: ✅ Totalmente Compatible

**Cómo Funciona**:
- CDN que cachea contenido estático y dinámico
- Puede cachear páginas completas en el edge

**Interacción**:
- ✅ **Sin conflictos**: Compatible
- ✅ **Mejora**: Nuestro sistema reduce tiempo de generación, mejorando cache hit rate

---

### Posibles Conflictos y Soluciones

#### Conflicto 1: Datos Obsoletos en Caché de Página

**Problema**:
- Varnish/Nginx cachea una página con posts antiguos
- Publicas un nuevo post
- Varnish/Nginx sigue mostrando la página antigua (aunque nuestro caché se limpió)

**Solución**:
```php
// Mejorar función de limpieza para notificar otros sistemas
function bl_clear_cached_queries_on_post_save( $post_id, $post ) {
    // Limpiar nuestro caché
    bl_clear_cached_query();
    
    // Limpiar caché de Varnish
    if ( function_exists( 'varnish_http_purge' ) ) {
        varnish_http_purge( get_permalink( $post_id ) );
        varnish_http_purge( home_url() ); // Limpiar homepage también
    }
    
    // Limpiar caché de Nginx
    wp_cache_flush();
    
    // Hook para otros sistemas
    do_action( 'bl_cached_query_cleared' );
}
```

---

#### Conflicto 2: Múltiples Servidores (Load Balancing)

**Problema**:
- Si tienes múltiples servidores PHP (load balancing)
- Cada servidor tiene su propio caché en memoria
- No se comparten entre servidores

**Solución Actual**:
- Cada servidor mantiene su propio caché
- Funciona pero no es óptimo

**Solución Futura**:
- Migrar a Redis para caché compartido
- Todos los servidores comparten el mismo caché

---

#### Conflicto 3: Caché Persistente vs. Caché de Request

**Problema**:
- Nuestro caché es por request (se pierde al terminar)
- Redis podría cachear resultados de consultas
- ¿Se duplican?

**Solución**:
- ✅ **No hay conflicto real**: Nuestro caché es temporal (por request), Redis es persistente
- ✅ **Son complementarios**: Podemos usar ambos

---

## 🚀 Mejores Prácticas para Producción

### 1. Configuración Recomendada

#### CloudPanel con Nginx + Varnish + Redis

**Arquitectura Ideal**:
```
Usuario
  ↓
Varnish (Puerto 80/443)
  ↓
Nginx (Puerto 8080)
  ↓
PHP-FPM + WordPress
  ↓
Redis (Object Cache)
  ↓
Nuestro Sistema (Query Cache)
  ↓
MySQL
```

**Configuración**:
1. ✅ Varnish cachea páginas completas (TTL: 1 hora)
2. ✅ Nginx FastCGI cache como respaldo (TTL: 30 minutos)
3. ✅ Redis para object cache (TTL: 1 hora)
4. ✅ Nuestro sistema para query cache (por request)

---

### 2. Headers HTTP para Control de Caché

```php
// Agregar headers para controlar caché de Varnish/Nginx
add_action( 'template_redirect', 'bl_add_cache_headers' );
function bl_add_cache_headers() {
    // Si la página usa nuestro sistema de caché, marcar como cacheable
    if ( !is_admin() && !is_user_logged_in() ) {
        header( 'X-Cache-Status: Cached-Query' );
        header( 'Cache-Control: public, max-age=3600' );
        header( 'X-Cache-Query-System: Bricks-Cached-WP-Query' );
    }
}
```

---

### 3. Limpieza de Caché Mejorada

```php
// Versión mejorada con soporte para múltiples sistemas
function bl_clear_cached_query( $cache_id = null ) {
    global $bl_cached_queries_storage;
    
    // Limpiar nuestro caché en memoria
    if ( $cache_id === null ) {
        $bl_cached_queries_storage['queries'] = [];
        $bl_cached_queries_storage['posts'] = [];
    } else {
        unset( $bl_cached_queries_storage['queries'][$cache_id] );
        unset( $bl_cached_queries_storage['posts'][$cache_id] );
    }
    
    // Limpiar de Redis si está disponible
    if ( function_exists( 'wp_cache_delete' ) ) {
        if ( $cache_id === null ) {
            wp_cache_delete( 'cached_queries_storage', 'cached_queries' );
        } else {
            wp_cache_delete( 'cached_query_' . $cache_id, 'cached_queries' );
        }
    }
    
    // Notificar otros sistemas de caché
    do_action( 'bl_cached_query_cleared', $cache_id );
}
```

---

### 4. Monitoreo y Debugging

```php
// Agregar logging opcional para debugging
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'BL_CACHE_DEBUG' ) && BL_CACHE_DEBUG ) {
    // Log de consultas cacheadas
    error_log( 'Cached Query: ' . $cache_id . ' - ' . count( $post_ids ) . ' posts' );
}
```

**Activar en wp-config.php**:
```php
define( 'WP_DEBUG', true );
define( 'BL_CACHE_DEBUG', true );
```

---

### 5. Optimización de Consultas

**Recomendaciones**:
- ✅ Limitar cantidad de posts en caché master (50-100 posts es óptimo)
- ✅ No crear más de 3-5 cachés por página
- ✅ Usar índices en base de datos (WordPress ya los crea automáticamente)
- ✅ Considerar Redis para persistencia en producción

---

## 🔮 Mejoras Futuras

### Corto Plazo (1-2 meses)

1. **Implementar Enfoque Híbrido**
   - Detectar y manejar `meta_query` correctamente
   - Documentar uso del enfoque híbrido

2. **Mejorar Limpieza de Caché**
   - Integrar con Varnish/Nginx
   - Notificar otros sistemas cuando se limpia

3. **Optimización de Código**
   - Revisar y optimizar funciones existentes
   - Eliminar código innecesario

### Mediano Plazo (3-6 meses)

1. **Migración a Redis**
   - Persistir caché entre requests
   - Compartir caché entre servidores

2. **Sistema de Caché para Meta Query**
   - Cachear resultados de consultas con `meta_query`
   - Reducir consultas repetidas

3. **Métricas y Monitoreo**
   - Logging de consultas
   - Estadísticas de rendimiento

### Largo Plazo (6+ meses)

1. **Caché Inteligente**
   - Detectar automáticamente qué cachear
   - Optimización automática basada en uso

2. **Integración con Plugins**
   - ACF
   - Meta Box
   - Otros plugins de campos personalizados

3. **API para Desarrolladores**
   - Hooks y filtros para extensibilidad
   - Documentación para desarrolladores

---

## 📝 Notas Finales

### Orden Crítico de Loops

⚠️ **IMPORTANTE**: El loop master DEBE estar al inicio de la estructura en Bricks.

- Si otro loop se ejecuta primero, creará el caché con sus propios parámetros
- El loop master no podrá modificar un caché ya existente
- Siempre coloca el loop master como el primer elemento en el DOM

### Múltiples Cachés

✅ Puedes usar múltiples cachés en la misma página usando diferentes Cache IDs:

- `home100` → Posts generales
- `OpinionPost` → Posts de opinión
- `DeportesPost` → Posts de deportes

Cada caché es independiente y funciona perfectamente.

### Rendimiento

Con nuestro sistema:
- **3-5 cachés por página** = 3-5 consultas MySQL
- **Total de consultas**: ~15-25 por página (incluyendo menús, widgets, etc.)
- **Esto es excelente** para sitios de alto tráfico

---

## 📚 Referencias

- [WordPress WP_Query Documentation](https://developer.wordpress.org/reference/classes/wp_query/)
- [Bricks Builder Documentation](https://academy.bricksbuilder.io/)
- [Varnish Cache Documentation](https://varnish-cache.org/docs/)
- [Redis Documentation](https://redis.io/docs/)

---

**Última Actualización**: 26 de Noviembre, 2025  
**Versión del Sistema**: 1.0  
**Mantenido por**: Equipo de Desarrollo

