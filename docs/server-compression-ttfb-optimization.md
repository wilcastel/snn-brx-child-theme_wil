# Optimización de Compresión y TTFB (Time To First Byte)

## 🎯 Problema Identificado por Lighthouse

**Document request latency - Est savings of 147 KiB**

### Problemas Detectados:
1. ✅ **Avoids redirects** - OK
2. ❌ **Server responded slowly (observed 1960 ms)** - CRÍTICO
3. ❌ **No compression applied** - CRÍTICO

---

## 📊 Análisis del Problema

### 1. TTFB Alto (1960 ms)

**TTFB (Time To First Byte)** es el tiempo que tarda el servidor en responder a la primera petición. Un TTFB de 1960 ms es **MUY ALTO** (ideal: < 200 ms).

#### Causas Posibles:
- **Base de datos lenta**: Queries no optimizadas
- **PHP lento**: Código no optimizado, plugins pesados
- **Servidor lento**: Recursos insuficientes (CPU, RAM)
- **Sin caché**: Cada petición procesa todo desde cero
- **Red lenta**: Latencia de red entre servidor y cliente

### 2. Sin Compresión (147 KiB sin comprimir)

**Compresión de texto** reduce el tamaño de HTML, CSS, JS en ~70-80%. Sin compresión, se envían 147 KiB que podrían ser ~30-40 KiB.

#### Tipos de Compresión:
- **Gzip**: Compatible universalmente, compresión ~70%
- **Brotli**: Mejor compresión (~75-80%), requiere servidor moderno

---

## 🔧 Soluciones por Nivel

### Nivel 1: Configuración del Servidor (RECOMENDADO)

#### Apache (.htaccess)

Agrega esto en el `.htaccess` de la raíz de WordPress, **ANTES** de las reglas de WordPress:

```apache
# ============================================
# COMPRESIÓN DE TEXTO (Gzip)
# ============================================
<IfModule mod_deflate.c>
    # Comprimir HTML, CSS, JavaScript, Text, XML y fuentes
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/atom+xml
    AddOutputFilterByType DEFLATE image/svg+xml
    AddOutputFilterByType DEFLATE font/ttf
    AddOutputFilterByType DEFLATE font/otf
    AddOutputFilterByType DEFLATE font/woff
    AddOutputFilterByType DEFLATE font/woff2
    
    # NO comprimir imágenes (ya están comprimidas)
    SetEnvIfNoCase Request_URI \.(?:gif|jpe?g|png|webp|ico)$ no-gzip dont-vary
    SetEnvIfNoCase Request_URI \.(?:exe|t?gz|zip|bz2|sit|rar)$ no-gzip dont-vary
    
    # Navegadores antiguos
    BrowserMatch ^Mozilla/4 gzip-only-text/html
    BrowserMatch ^Mozilla/4\.0[678] no-gzip
    BrowserMatch \bMSIE !no-gzip !gzip-only-text/html
    
    # Headers para caché
    Header append Vary Accept-Encoding
</IfModule>

# ============================================
# COMPRESIÓN BROTLI (Mejor que Gzip, si está disponible)
# ============================================
<IfModule mod_brotli.c>
    AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/xml text/css text/javascript application/javascript application/json application/xml application/xhtml+xml application/rss+xml application/atom+xml image/svg+xml font/ttf font/otf font/woff font/woff2
</IfModule>

# ============================================
# CACHÉ DE ARCHIVOS ESTÁTICOS
# ============================================
<IfModule mod_expires.c>
    ExpiresActive On
    
    # Imágenes
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/x-icon "access plus 1 year"
    
    # CSS y JavaScript
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/x-javascript "access plus 1 month"
    
    # Fuentes
    ExpiresByType font/ttf "access plus 1 year"
    ExpiresByType font/otf "access plus 1 year"
    ExpiresByType font/woff "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
    
    # HTML (corto, porque puede cambiar)
    ExpiresByType text/html "access plus 0 seconds"
</IfModule>

# ============================================
# HEADERS DE CACHÉ
# ============================================
<IfModule mod_headers.c>
    # Cache-Control para archivos estáticos
    <FilesMatch "\.(jpg|jpeg|png|gif|webp|ico|svg|css|js|woff|woff2|ttf|otf)$">
        Header set Cache-Control "max-age=31536000, public, immutable"
    </FilesMatch>
    
    # Cache-Control para HTML (corto)
    <FilesMatch "\.(html|htm)$">
        Header set Cache-Control "max-age=0, must-revalidate"
    </FilesMatch>
</IfModule>
```

#### Nginx

Agrega esto en la configuración de Nginx del sitio:

```nginx
# ============================================
# COMPRESIÓN GZIP
# ============================================
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml text/javascript 
           application/json application/javascript application/xml+rss 
           application/rss+xml font/truetype font/opentype 
           application/vnd.ms-fontobject image/svg+xml;
gzip_disable "msie6";

# ============================================
# COMPRESIÓN BROTLI (Mejor que Gzip)
# ============================================
brotli on;
brotli_comp_level 6;
brotli_types text/plain text/css text/xml text/javascript 
             application/json application/javascript application/xml+rss 
             application/rss+xml font/truetype font/opentype 
             application/vnd.ms-fontobject image/svg+xml;

# ============================================
# CACHÉ DE ARCHIVOS ESTÁTICOS
# ============================================
location ~* \.(jpg|jpeg|png|gif|webp|ico|svg|css|js|woff|woff2|ttf|otf)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    access_log off;
}

# ============================================
# CACHÉ DE HTML (corto)
# ============================================
location ~* \.(html|htm)$ {
    expires 0;
    add_header Cache-Control "no-cache, must-revalidate";
}
```

---

### Nivel 2: Optimizaciones desde WordPress (Theme)

#### A. Reducir TTFB desde PHP

**Ya implementado en el theme:**
- ✅ Sistema de cache de queries (`cached-wp-query.php`)
- ✅ Precarga de términos, imágenes, autores
- ✅ Optimización del head

**Pendiente de implementar:**
- ⚠️ **Object Cache (Redis/Memcached)** - Ya documentado en `docs/redis-integration-explained.md`
- ⚠️ **Page Cache (Varnish/Nginx Cache)** - Requiere configuración del servidor

#### B. Compresión desde PHP (NO RECOMENDADO)

**⚠️ ADVERTENCIA**: La compresión desde PHP es menos eficiente que la del servidor. Solo usar si no puedes configurar el servidor.

```php
// NO implementar esto si ya tienes compresión en el servidor
// Solo usar como último recurso

// En functions.php o code snippet
if (!ob_get_level() && !headers_sent() && extension_loaded('zlib')) {
    // Verificar si el navegador acepta compresión
    if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
        ob_start('ob_gzhandler');
    } else {
        ob_start();
    }
}
```

**Problemas de compresión desde PHP:**
- Se ejecuta después de que WordPress procesa todo
- No comprime archivos estáticos (CSS, JS)
- Menos eficiente que compresión del servidor
- Puede causar problemas con algunos plugins

---

### Nivel 3: Optimizaciones Avanzadas

#### A. Redis Object Cache (Para TTFB)

**Beneficios:**
- Reduce queries a la base de datos
- Cachea objetos de WordPress en memoria
- Mejora TTFB significativamente

**Documentación:** Ver `docs/redis-integration-explained.md`

#### B. Varnish/Nginx Cache (Para TTFB)

**Beneficios:**
- Cachea páginas completas en memoria
- Sirve HTML sin procesar PHP
- TTFB < 50 ms típicamente

**Requisitos:**
- Acceso root al servidor
- Configuración de Varnish o Nginx Cache

#### C. CDN (Para TTFB Global)

**Beneficios:**
- Reduce latencia geográfica
- Comprime automáticamente
- Cachea en múltiples ubicaciones

**Opciones:**
- Cloudflare (gratis, con compresión automática)
- KeyCDN
- BunnyCDN

---

## 📈 Resultados Esperados

### Con Compresión del Servidor:
- **Tamaño HTML**: 147 KiB → ~30-40 KiB (ahorro ~70%)
- **Tamaño CSS**: Reducción ~70-80%
- **Tamaño JS**: Reducción ~70-80%

### Con Redis Object Cache:
- **TTFB**: 1960 ms → ~300-500 ms (mejora ~75%)
- **Queries**: Reducción significativa

### Con Varnish/Nginx Cache:
- **TTFB**: 1960 ms → ~50-200 ms (mejora ~90%)
- **Carga de página**: Mucho más rápida

---

## 🎯 Plan de Acción Recomendado

### Fase 1: Inmediato (Sin acceso root)
1. ✅ **Configurar compresión en Apache** (si tienes acceso a .htaccess)
2. ⚠️ **Implementar Redis Object Cache** (requiere plugin y servidor Redis)

### Fase 2: Corto Plazo (Con acceso root)
1. ⚠️ **Configurar compresión en Nginx** (producción)
2. ⚠️ **Configurar Varnish o Nginx Cache** (para cache de páginas)

### Fase 3: Largo Plazo
1. ⚠️ **CDN con compresión automática** (Cloudflare, etc.)
2. ⚠️ **Optimizar hosting** (managed WordPress hosting)

---

## 🔍 Verificación

### Verificar Compresión:

**Opción 1: Navegador (DevTools)**
1. Abre DevTools (F12)
2. Ve a Network
3. Recarga la página
4. Busca el archivo HTML principal
5. En "Response Headers", busca:
   - `Content-Encoding: gzip` o `Content-Encoding: br` (Brotli)

**Opción 2: cURL**
```bash
curl -H "Accept-Encoding: gzip" -I https://tudominio.com
# Busca: Content-Encoding: gzip
```

**Opción 3: Herramientas Online**
- https://www.giftofspeed.com/gzip-test/
- https://tools.pingdom.com/

### Verificar TTFB:

**Opción 1: Lighthouse**
- Ejecuta Lighthouse
- Busca "Time to First Byte" en Performance

**Opción 2: DevTools**
1. Abre DevTools (F12)
2. Ve a Network
3. Recarga la página
4. Busca el documento principal (HTML)
5. Mira "Waiting (TTFB)"

---

## ⚠️ Notas Importantes

1. **Compresión del servidor > Compresión PHP**: Siempre preferir compresión del servidor
2. **No duplicar compresión**: Si el servidor comprime, NO comprimir desde PHP
3. **TTFB alto = Problema de servidor**: 1960 ms indica problema serio de servidor/código
4. **Redis ayuda pero no es suficiente**: Para TTFB < 200 ms, necesitas page cache (Varnish/Nginx)

---

## 📚 Referencias

- [WordPress Performance Team - Compression](https://make.wordpress.org/core/handbook/best-practices/performance/)
- [Google - Enable Text Compression](https://web.dev/uses-text-compression/)
- [MDN - HTTP Compression](https://developer.mozilla.org/en-US/docs/Web/HTTP/Compression)


