# Integración con Redis: Explicación Detallada

## 📚 Conceptos Básicos

### ¿Qué es Redis?

**Redis** (Remote Dictionary Server) es una base de datos en memoria (in-memory) que funciona como un almacén de clave-valor ultra rápido.

**Características principales**:
- ⚡ **Ultra rápido**: Todo está en RAM (memoria)
- 💾 **Persistente**: Puede guardar datos en disco (opcional)
- 🔄 **Compartido**: Múltiples servidores pueden acceder al mismo Redis
- ⏱️ **TTL**: Expiración automática de datos (Time To Live)

---

## 🔄 Sistema Actual vs. Sistema con Redis

### Sistema Actual (Cache por Request)

```
Request 1:
  ↓
PHP inicia
  ↓
Variable global: $bl_cached_queries_storage = []
  ↓
Crea cache: $bl_cached_queries_storage['posts']['home100'] = [posts...]
  ↓
Reutiliza cache en múltiples loops
  ↓
Request termina → Variable se pierde ❌
  ↓
Request 2:
  ↓
PHP inicia de nuevo
  ↓
Variable global: $bl_cached_queries_storage = [] (vacía)
  ↓
Tiene que crear el cache de nuevo ❌
```

**Problemas**:
- ❌ Cache se pierde al terminar el request
- ❌ Cada request tiene que crear el cache desde cero
- ❌ No se comparte entre múltiples servidores PHP
- ❌ Si tienes 2 servidores, cada uno tiene su propio cache

---

### Sistema con Redis (Cache Persistente)

```
Request 1:
  ↓
PHP inicia
  ↓
Intenta obtener de Redis: wp_cache_get('cached_query_home100')
  ↓
¿Existe en Redis? NO
  ↓
Crea cache: Query MySQL → Obtiene 100 posts
  ↓
Guarda en Redis: wp_cache_set('cached_query_home100', [posts...], TTL: 3600)
  ↓
Reutiliza cache en múltiples loops
  ↓
Request termina → Cache queda en Redis ✅
  ↓
Request 2 (mismo servidor o diferente):
  ↓
PHP inicia
  ↓
Intenta obtener de Redis: wp_cache_get('cached_query_home100')
  ↓
¿Existe en Redis? SÍ ✅
  ↓
Obtiene cache de Redis (sin query MySQL) ⚡
  ↓
Reutiliza cache en múltiples loops
  ↓
Request termina → Cache sigue en Redis ✅
```

**Ventajas**:
- ✅ Cache persiste entre requests
- ✅ Se comparte entre múltiples servidores PHP
- ✅ Si un servidor crea el cache, todos los demás lo pueden usar
- ✅ TTL automático (expira después de X tiempo)

---

## 🏗️ Arquitectura con Redis

### Stack Completo

```
┌─────────────────────────────────────────────────────────┐
│                    USUARIO                               │
└────────────────────┬────────────────────────────────────┘
                     │
                     ↓
┌─────────────────────────────────────────────────────────┐
│              VARNISH (Page Cache)                       │
│  - Cachea HTML completo                                 │
│  - Si existe → Devuelve HTML (no llega a WordPress)     │
│  - Si no existe → Pasa a Nginx                          │
└────────────────────┬────────────────────────────────────┘
                     │
                     ↓
┌─────────────────────────────────────────────────────────┐
│              NGINX (Reverse Proxy)                       │
│  - FastCGI Cache (backup)                               │
│  - Balanceador de carga                                 │
└────────────────────┬────────────────────────────────────┘
                     │
                     ↓
┌─────────────────────────────────────────────────────────┐
│         PHP-FPM + WORDPRESS                             │
│                                                          │
│  ┌──────────────────────────────────────────────┐      │
│  │  Nuestro Sistema (Query Cache)               │      │
│  │  - Intenta obtener de Redis                  │      │
│  │  - Si no existe → Query MySQL                │      │
│  │  - Guarda en Redis                           │      │
│  └──────────────┬───────────────────────────────┘      │
│                 │                                       │
│                 ↓                                       │
│  ┌──────────────────────────────────────────────┐      │
│  │  REDIS (Object Cache)                         │      │
│  │  - Almacena cache de queries                  │      │
│  │  - Compartido entre servidores                │      │
│  │  - TTL automático                             │      │
│  └──────────────┬───────────────────────────────┘      │
│                 │                                       │
│                 ↓                                       │
│  ┌──────────────────────────────────────────────┐      │
│  │  MYSQL (Database)                             │      │
│  │  - Solo se consulta si no hay cache          │      │
│  └──────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────┘
```

---

## 🔧 Qué se Necesita

### 1. Servidor Redis

**Opciones**:

#### Opción A: Redis en el mismo servidor (Desarrollo/Pequeño)
```bash
# Instalar Redis
sudo apt-get install redis-server

# Iniciar Redis
sudo systemctl start redis-server

# Verificar que funciona
redis-cli ping
# Debe responder: PONG
```

#### Opción B: Redis en servidor separado (Producción)
- Servidor dedicado para Redis
- Mejor para alta disponibilidad
- Puede ser compartido entre múltiples sitios

#### Opción C: Redis Managed (Cloud)
- AWS ElastiCache
- Google Cloud Memorystore
- DigitalOcean Managed Redis
- Azure Cache for Redis

### 2. Plugin de WordPress para Redis

**Opciones**:

#### Opción A: Redis Object Cache (Recomendado)
- Plugin oficial de WordPress
- Gratis y open source
- Muy popular y mantenido
- Instalación: `wp plugin install redis-cache`

#### Opción B: WP Redis
- Alternativa ligera
- Menos features pero más simple

### 3. Configuración en WordPress

**En `wp-config.php`**:
```php
// Habilitar Redis Object Cache
define('WP_REDIS_HOST', '127.0.0.1'); // IP de Redis
define('WP_REDIS_PORT', 6379); // Puerto de Redis
define('WP_REDIS_DATABASE', 0); // Base de datos (0-15)
define('WP_REDIS_PASSWORD', ''); // Contraseña (si tiene)
define('WP_REDIS_TIMEOUT', 1); // Timeout en segundos
define('WP_REDIS_READ_TIMEOUT', 1);
define('WP_REDIS_DATABASE', 0);
```

---

## 💻 Cómo Funcionaría el Código

### Sistema Actual (Sin Redis)

```php
// includes/cached-wp-query.php

function bl_get_cached_queries_storage() {
    global $bl_cached_queries_storage;
    
    if ( !isset( $bl_cached_queries_storage ) ) {
        // Variable global (se pierde al terminar request)
        $bl_cached_queries_storage = [
            'posts' => [],
            'queries' => [],
        ];
    }
    
    return $bl_cached_queries_storage;
}

// Guardar cache
function bl_save_cache($cache_id, $posts) {
    global $bl_cached_queries_storage;
    $bl_cached_queries_storage['posts'][$cache_id] = $posts;
    // ❌ Se pierde al terminar request
}

// Obtener cache
function bl_get_cache($cache_id) {
    global $bl_cached_queries_storage;
    return $bl_cached_queries_storage['posts'][$cache_id] ?? null;
    // ❌ Solo existe durante el request actual
}
```

### Sistema con Redis

```php
// includes/cached-wp-query.php

function bl_get_cached_queries_storage() {
    global $bl_cached_queries_storage;
    
    if ( !isset( $bl_cached_queries_storage ) ) {
        // Primero intentar obtener de Redis
        $cached = wp_cache_get( 'bl_cached_queries_storage', 'cached_queries' );
        
        if ( $cached !== false ) {
            // ✅ Cache existe en Redis, usarlo
            $bl_cached_queries_storage = $cached;
        } else {
            // ❌ No existe en Redis, crear nuevo
            $bl_cached_queries_storage = [
                'posts' => [],
                'queries' => [],
            ];
        }
    }
    
    return $bl_cached_queries_storage;
}

// Guardar cache
function bl_save_cache($cache_id, $posts) {
    global $bl_cached_queries_storage;
    
    // Guardar en variable global (para este request)
    $bl_cached_queries_storage['posts'][$cache_id] = $posts;
    
    // ✅ Guardar también en Redis (persiste entre requests)
    wp_cache_set( 
        'bl_cached_queries_storage', 
        $bl_cached_queries_storage, 
        'cached_queries', 
        3600 // TTL: 1 hora
    );
}

// Obtener cache
function bl_get_cache($cache_id) {
    global $bl_cached_queries_storage;
    
    // Primero verificar variable global (más rápido)
    if ( isset( $bl_cached_queries_storage['posts'][$cache_id] ) ) {
        return $bl_cached_queries_storage['posts'][$cache_id];
    }
    
    // Si no existe, intentar obtener de Redis
    $cached = wp_cache_get( 'bl_cached_queries_storage', 'cached_queries' );
    if ( $cached !== false && isset( $cached['posts'][$cache_id] ) ) {
        // ✅ Encontrado en Redis, actualizar variable global
        $bl_cached_queries_storage = $cached;
        return $cached['posts'][$cache_id];
    }
    
    return null;
}
```

---

## 📊 Flujo Completo con Redis

### Escenario 1: Primer Request (Cache Vacío)

```
1. Usuario visita homepage
   ↓
2. Varnish: ¿Cache existe? NO
   ↓
3. Pasa a WordPress
   ↓
4. Nuestro sistema: bl_get_cache('home100')
   ↓
5. Variable global: ¿Existe? NO
   ↓
6. Redis: wp_cache_get('bl_cached_queries_storage')
   ↓
7. Redis: ¿Existe? NO
   ↓
8. Query MySQL: SELECT * FROM wp_posts WHERE...
   ↓
9. Obtiene 100 posts
   ↓
10. Guarda en variable global
   ↓
11. Guarda en Redis: wp_cache_set(..., TTL: 3600)
   ↓
12. Renderiza página
   ↓
13. Varnish guarda HTML en cache
   ↓
14. Devuelve HTML al usuario
```

**Tiempo**: ~2 segundos (query MySQL + renderizado)

---

### Escenario 2: Segundo Request (Cache en Redis)

```
1. Usuario visita homepage
   ↓
2. Varnish: ¿Cache existe? SÍ ✅
   ↓
3. Devuelve HTML directamente (no llega a WordPress)
   ↓
4. Usuario recibe página en ~50ms ⚡
```

**Tiempo**: ~50ms (solo Varnish)

---

### Escenario 3: Request después de TTL (Cache Expirado)

```
1. Usuario visita homepage
   ↓
2. Varnish: ¿Cache existe? NO (expiró)
   ↓
3. Pasa a WordPress
   ↓
4. Nuestro sistema: bl_get_cache('home100')
   ↓
5. Variable global: ¿Existe? NO
   ↓
6. Redis: wp_cache_get('bl_cached_queries_storage')
   ↓
7. Redis: ¿Existe? NO (expiró después de 1 hora)
   ↓
8. Query MySQL: SELECT * FROM wp_posts WHERE...
   ↓
9. (Repite proceso del Escenario 1)
```

**Tiempo**: ~2 segundos (pero solo cada hora)

---

## 🎯 Beneficios Concretos

### Sin Redis (Sistema Actual)

| Métrica | Valor |
|---------|-------|
| Requests que crean cache | 100% (todos) |
| Queries MySQL por hora | ~3,600 (si tienes 1 req/seg) |
| Tiempo promedio | ~2 segundos |
| Cache compartido | ❌ No |

### Con Redis

| Métrica | Valor |
|---------|-------|
| Requests que crean cache | ~0.03% (solo cuando expira) |
| Queries MySQL por hora | ~1 (solo cuando expira) |
| Tiempo promedio | ~50ms (si Varnish cachea) |
| Cache compartido | ✅ Sí |

**Mejora**: **97% menos queries MySQL** 🚀

---

## ⚙️ Configuración Recomendada

### TTL (Time To Live)

```php
// Cache de queries: 1 hora
wp_cache_set( 'bl_cached_queries_storage', $data, 'cached_queries', 3600 );

// Razón: 
// - Posts no cambian frecuentemente
// - 1 hora es un buen balance entre frescura y rendimiento
// - Se limpia automáticamente cuando se publica contenido
```

### Limpieza de Cache

```php
// Cuando se publica/actualiza contenido
function bl_clear_cached_queries_on_post_save( $post_id, $post ) {
    // Limpiar variable global
    global $bl_cached_queries_storage;
    $bl_cached_queries_storage = null;
    
    // Limpiar Redis
    wp_cache_delete( 'bl_cached_queries_storage', 'cached_queries' );
    
    // Limpiar Varnish (si está disponible)
    if ( function_exists( 'varnish_http_purge' ) ) {
        varnish_http_purge( home_url() );
    }
}
```

---

## 🔍 Monitoreo y Debugging

### Verificar que Redis Funciona

```php
// En wp-config.php (temporalmente)
if ( function_exists( 'wp_cache_get' ) ) {
    $test = wp_cache_set( 'test_key', 'test_value', 'test_group', 60 );
    $result = wp_cache_get( 'test_key', 'test_group' );
    
    if ( $result === 'test_value' ) {
        error_log( '✅ Redis funciona correctamente' );
    } else {
        error_log( '❌ Redis NO funciona' );
    }
}
```

### Ver Contenido de Redis

```bash
# Conectar a Redis
redis-cli

# Ver todas las claves
KEYS *

# Ver contenido de una clave
GET bl_cached_queries_storage

# Ver TTL de una clave
TTL bl_cached_queries_storage

# Eliminar una clave
DEL bl_cached_queries_storage
```

---

## 💰 Costos y Recursos

### Recursos Necesarios

**Redis en servidor compartido**:
- RAM: ~100-500 MB (depende del tamaño del cache)
- CPU: Mínimo (Redis es muy eficiente)
- Disco: Opcional (solo si habilitas persistencia)

**Redis Managed (Cloud)**:
- AWS ElastiCache: ~$15-50/mes
- DigitalOcean: ~$15/mes
- Google Cloud: ~$20-60/mes

### ROI (Return on Investment)

**Ahorro**:
- 97% menos queries MySQL
- 95% menos tiempo de respuesta
- Menor carga en servidor
- Mejor experiencia de usuario

**Costo**:
- Tiempo de implementación: 2-3 días
- Mantenimiento: Mínimo (Redis es muy estable)

---

## 🚨 Consideraciones Importantes

### 1. Memoria de Redis

**Problema**: Redis guarda todo en RAM. Si tienes mucho contenido, puede llenarse.

**Solución**: Configurar límite de memoria y política de evicción:
```bash
# En redis.conf
maxmemory 512mb
maxmemory-policy allkeys-lru  # Elimina claves menos usadas cuando se llena
```

### 2. Persistencia

**Por defecto**: Redis guarda en RAM (se pierde si se reinicia).

**Opciones**:
- **RDB**: Snapshot periódico (cada X minutos)
- **AOF**: Append Only File (guarda cada comando)
- **Ambos**: Máxima seguridad

### 3. Alta Disponibilidad

**Problema**: Si Redis se cae, todo el sitio se vuelve lento.

**Solución**: 
- Redis Sentinel (monitoreo automático)
- Redis Cluster (múltiples instancias)
- Fallback a sistema actual si Redis falla

---

## 📝 Resumen

### ¿Qué es Redis?
Base de datos en memoria ultra rápida que persiste datos entre requests.

### ¿Qué se necesita?
1. Servidor Redis (o servicio managed)
2. Plugin de WordPress (Redis Object Cache)
3. Configuración en wp-config.php

### ¿Cómo funciona?
1. Primer request: Crea cache → Guarda en Redis
2. Requests siguientes: Obtiene de Redis (sin MySQL)
3. Cuando expira: Crea cache de nuevo

### ¿Vale la pena?
**SÍ**, especialmente si:
- Tienes 100k+ posts
- Múltiples servidores PHP
- Alto tráfico
- Necesitas mejor rendimiento

### ¿Cuándo implementarlo?
**Ahora** si:
- El sistema actual funciona pero quieres mejorar más
- Tienes múltiples servidores
- Quieres reducir carga en MySQL

**Después** si:
- El sistema actual es suficiente
- No tienes múltiples servidores
- No tienes presupuesto para Redis managed

