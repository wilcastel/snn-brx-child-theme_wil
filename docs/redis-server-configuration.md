# Configuración de Redis en CloudPanel (AWS)

## 📋 Resumen

Esta guía te ayudará a configurar Redis en tu servidor CloudPanel en AWS para optimizar WordPress y reducir el consumo de recursos.

## 🎯 Objetivos

- ✅ Configurar Redis como Object Cache para WordPress
- ✅ Reducir consultas MySQL en un 90%+
- ✅ Compartir cache entre múltiples servidores PHP
- ✅ Optimizar el consumo de recursos del servidor

---

## 🔧 Paso 1: Verificar Instalación de Redis

### En CloudPanel (SSH)

```bash
# Verificar si Redis está instalado
redis-cli ping
# Debe responder: PONG

# Verificar versión
redis-cli --version

# Verificar estado del servicio
sudo systemctl status redis
```

### Si Redis NO está instalado:

```bash
# Instalar Redis
sudo apt update
sudo apt install redis-server -y

# Iniciar Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Verificar instalación
redis-cli ping
```

---

## 🔧 Paso 2: Verificar Extensión PHP Redis

### Verificar si php-redis está instalado:

```bash
# Verificar extensión PHP 8.3
php8.3 -m | grep redis

# Si no está instalado, instalar:
sudo apt install php8.3-redis -y

# Reiniciar PHP-FPM
sudo systemctl restart php8.3-fpm
```

### Verificar en CloudPanel:

1. Ve a **PHP** → Selecciona PHP 8.3
2. Busca la extensión **redis**
3. Si no está, actívala desde ahí

---

## 🔧 Paso 3: Configurar Redis

### Editar configuración de Redis:

```bash
sudo nano /etc/redis/redis.conf
```

### Configuraciones importantes:

```conf
# Límite de memoria (ajusta según tu servidor)
# Ejemplo para servidor con 4GB RAM: usar 512MB para Redis
maxmemory 512mb
maxmemory-policy allkeys-lru

# Persistencia (opcional, pero recomendado)
save 900 1      # Guardar cada 15 minutos si hay al menos 1 cambio
save 300 10     # Guardar cada 5 minutos si hay al menos 10 cambios
save 60 10000   # Guardar cada minuto si hay al menos 10000 cambios

# Puerto (por defecto 6379, mantener así)
port 6379

# Bind solo a localhost (seguridad)
bind 127.0.0.1

# Timeout de conexión
timeout 300
```

### Reiniciar Redis:

```bash
sudo systemctl restart redis-server
sudo systemctl status redis-server
```

---

## 🔧 Paso 4: Configurar WordPress

### 1. Agregar configuración a `wp-config.php`

Abre `wp-config.php` y agrega ANTES de `/* That's all, stop editing! */`:

```php
// ============================================
// CONFIGURACIÓN DE REDIS
// ============================================

// Host de Redis
define( 'WP_REDIS_HOST', '127.0.0.1' );
define( 'WP_REDIS_PORT', 6379 );
define( 'WP_REDIS_DATABASE', 0 );
define( 'WP_REDIS_PASSWORD', '' );
define( 'WP_REDIS_TIMEOUT', 1 );
define( 'WP_REDIS_READ_TIMEOUT', 1 );

// Prefijo para evitar conflictos
$redis_prefix = defined( 'DB_NAME' ) ? DB_NAME : 'lanacion';
define( 'WP_REDIS_PREFIX', $redis_prefix . ':' );

// Configuración para nuestro tema
define( 'REDIS_HOST', '127.0.0.1' );
define( 'REDIS_PORT', 6379 );
$site_prefix = defined( 'DB_NAME' ) ? DB_NAME : 'lanacion';
define( 'REDIS_SITE_PREFIX', $site_prefix . '_visits_' );
```

### 2. Verificar que el tema carga los archivos correctos

El tema ya incluye:
- `includes/redis-cache-helper.php` - Helper para Redis
- `includes/cached-wp-query.php` - Sistema de cache de queries (modificado para usar Redis)

---

## 🔧 Paso 5: Configurar Varnish (Opcional pero Recomendado)

### Verificar configuración de Varnish

Varnish ya está funcionando, pero podemos optimizarlo:

```bash
# Ver configuración actual
sudo nano /etc/varnish/default.vcl
```

### Configuración recomendada:

```vcl
# TTL para páginas de WordPress (1 hora)
sub vcl_backend_response {
    if (bereq.url ~ "^/") {
        set beresp.ttl = 1h;
        set beresp.grace = 1h;
    }
}

# Limpiar cache cuando se publica contenido
sub vcl_recv {
    if (req.method == "PURGE") {
        return (purge);
    }
}
```

---

## 🔧 Paso 6: Optimizar PHP-FPM

### Configuración recomendada para CloudPanel:

```bash
# Editar configuración PHP-FPM
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

### Ajustes importantes:

```ini
; Procesos PHP-FPM (ajusta según tu servidor)
pm = dynamic
pm.max_children = 50        # Máximo de procesos
pm.start_servers = 10      # Procesos al inicio
pm.min_spare_servers = 5    # Mínimo en espera
pm.max_spare_servers = 15   # Máximo en espera
pm.max_requests = 500       # Reiniciar proceso después de X requests

; Timeouts
request_terminate_timeout = 60s
```

### Reiniciar PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
```

---

## 🔧 Paso 7: Optimizar Nginx

### Configuración FastCGI Cache (si no usas Varnish):

```nginx
# En la configuración del sitio en CloudPanel
fastcgi_cache_path /var/cache/nginx levels=1:2 keys_zone=WORDPRESS:100m inactive=60m;
fastcgi_cache_key "$scheme$request_method$host$request_uri";

# En el bloque server
fastcgi_cache WORDPRESS;
fastcgi_cache_valid 200 60m;
fastcgi_cache_bypass $skip_cache;
fastcgi_no_cache $skip_cache;
```

---

## 📊 Paso 8: Verificar Funcionamiento

### 1. Verificar Redis:

```bash
# Conectar a Redis
redis-cli

# Ver todas las claves
KEYS *

# Ver estadísticas
INFO stats

# Ver uso de memoria
INFO memory

# Salir
exit
```

### 2. Verificar en WordPress:

Crea un archivo temporal `test-redis.php` en la raíz de WordPress:

```php
<?php
require_once('wp-load.php');

// Verificar Redis
if (function_exists('snn_redis_is_available')) {
    if (snn_redis_is_available()) {
        echo "✅ Redis está disponible\n";
        
        // Probar guardar y obtener
        snn_redis_set('test_key', 'test_value', 'test_group', 60);
        $value = snn_redis_get('test_key', 'test_group');
        
        if ($value === 'test_value') {
            echo "✅ Redis funciona correctamente\n";
        } else {
            echo "❌ Error: No se pudo obtener el valor\n";
        }
        
        // Estadísticas
        $stats = snn_redis_get_stats();
        if ($stats) {
            echo "\n📊 Estadísticas de Redis:\n";
            echo "Memoria usada: " . $stats['used_memory_human'] . "\n";
            echo "Claves totales: " . $stats['total_keys'] . "\n";
            echo "Hits: " . $stats['keyspace_hits'] . "\n";
            echo "Misses: " . $stats['keyspace_misses'] . "\n";
        }
    } else {
        echo "❌ Redis NO está disponible\n";
    }
} else {
    echo "⚠️ Función snn_redis_is_available no encontrada\n";
}
```

Accede a: `https://tudominio.com/test-redis.php`

**IMPORTANTE**: Elimina este archivo después de verificar.

---

## 🔍 Paso 9: Monitoreo

### Ver uso de Redis:

```bash
# Monitoreo en tiempo real
redis-cli --stat

# Ver claves específicas
redis-cli KEYS "lanacion_*"

# Ver tamaño de una clave
redis-cli MEMORY USAGE "lanacion_cache_cached_queries:bl_cached_queries_storage"
```

### Ver logs de Redis:

```bash
# Ver logs en tiempo real
sudo tail -f /var/log/redis/redis-server.log
```

### Ver uso de recursos:

```bash
# CPU y memoria
htop

# Procesos PHP
ps aux | grep php-fpm

# Conexiones Redis
redis-cli CLIENT LIST
```

---

## 🚨 Solución de Problemas

### Problema 1: Redis no conecta

**Solución:**
```bash
# Verificar que Redis está corriendo
sudo systemctl status redis-server

# Verificar puerto
sudo netstat -tlnp | grep 6379

# Ver logs
sudo tail -f /var/log/redis/redis-server.log
```

### Problema 2: PHP Redis no funciona

**Solución:**
```bash
# Verificar extensión
php8.3 -m | grep redis

# Reinstalar extensión
sudo apt remove php8.3-redis
sudo apt install php8.3-redis
sudo systemctl restart php8.3-fpm
```

### Problema 3: Redis se llena de memoria

**Solución:**
```bash
# Ver uso actual
redis-cli INFO memory

# Limpiar cache manualmente (CUIDADO: borra todo)
redis-cli FLUSHDB

# Ajustar límite en redis.conf
maxmemory 512mb
maxmemory-policy allkeys-lru
```

### Problema 4: Cache no se limpia cuando publicas contenido

**Solución:**
- Verificar que `bl_clear_cached_query()` se ejecuta
- Verificar logs de WordPress
- Verificar que Redis está funcionando

---

## 📈 Resultados Esperados

### Antes de Redis:
- ❌ 50-100+ queries MySQL por página
- ❌ 2-3 segundos tiempo de carga
- ❌ Alto consumo de CPU y memoria

### Después de Redis:
- ✅ 5-10 queries MySQL por página (90% reducción)
- ✅ 0.5-1 segundo tiempo de carga (con Varnish)
- ✅ 60-70% menos consumo de CPU y memoria

---

## 🔄 Mantenimiento

### Limpiar cache manualmente:

```bash
# Limpiar todo el cache de Redis
redis-cli FLUSHDB

# Limpiar solo cache de WordPress (más seguro)
redis-cli --scan --pattern "lanacion_*" | xargs redis-cli DEL
```

### Reiniciar servicios:

```bash
# Reiniciar Redis
sudo systemctl restart redis-server

# Reiniciar PHP-FPM
sudo systemctl restart php8.3-fpm

# Reiniciar Nginx
sudo systemctl restart nginx

# Reiniciar Varnish
sudo systemctl restart varnish
```

---

## 📚 Referencias

- [Redis Documentation](https://redis.io/docs/)
- [CloudPanel Documentation](https://www.cloudpanel.io/docs/)
- [WordPress Object Cache](https://developer.wordpress.org/reference/classes/wp_object_cache/)

---

**Última actualización**: Diciembre 2024  
**Versión**: 1.0

