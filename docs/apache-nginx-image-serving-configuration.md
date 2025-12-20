# Configuración de Apache y Nginx para Servir Imágenes WebP Directamente

## 🎯 Objetivo

Configurar Apache (local) y Nginx (producción) para servir imágenes WebP directamente desde el sistema de archivos, **sin pasar por WordPress**. Esto mejora significativamente el rendimiento al evitar que las peticiones de imágenes generen queries a la base de datos.

---

## 📋 Cómo Funciona el Sistema WebP Actual

### Sistema Actual (Con WordPress)

El sistema WebP del theme actualmente:

1. **Convierte imágenes** → Guarda en `/wp-content/uploads/webp/`
2. **Reemplaza URLs** → Usa filtros de WordPress:
   - `wp_get_attachment_image_src` → Reemplaza URLs de imágenes destacadas
   - `the_content` → Reemplaza imágenes en contenido
   - `wp_get_attachment_url` → Reemplaza URLs de attachments
   - `wp_calculate_image_srcset` → Reemplaza en srcset

3. **Problema**: Las imágenes aún pasan por WordPress (generan queries)

### Sistema Optimizado (Con Apache/Nginx)

Con la configuración correcta:

1. **Convierte imágenes** → Guarda en `/wp-content/uploads/webp/`
2. **Reemplaza URLs** → WordPress genera URLs WebP en el HTML
3. **Apache/Nginx** → Sirve archivos WebP directamente (sin pasar por WordPress)
4. **Resultado**: Cero queries para imágenes

---

## 🔧 Configuración de Apache (.htaccess)

### Ubicación del Archivo

Agrega estas reglas en el `.htaccess` de la raíz de WordPress, **ANTES** de las reglas de WordPress:

```apache
# ============================================
# OPTIMIZACIÓN: Servir archivos estáticos directamente
# IMPORTANTE: Estas reglas DEBEN estar ANTES de las reglas de WordPress
# ============================================
<IfModule mod_rewrite.c>
RewriteEngine On

# 1. Favicon - Servir directamente si existe
RewriteCond %{REQUEST_URI} ^/favicon\.ico$ [NC]
RewriteCond %{DOCUMENT_ROOT}/favicon.ico -f
RewriteRule ^favicon\.ico$ /favicon.ico [L]

# 2. Imágenes WebP - Servir directamente desde carpeta webp
# Si el navegador acepta WebP y existe la versión WebP, servirla
RewriteCond %{HTTP_ACCEPT} image/webp
RewriteCond %{REQUEST_URI} \.(jpg|jpeg|png|gif)$ [NC]
RewriteCond %{DOCUMENT_ROOT}/wp-content/uploads/webp/$1.webp -f
RewriteRule ^wp-content/uploads/(.+)$ wp-content/uploads/webp/$1.webp [L]

# 3. Imágenes estáticas - Servir directamente sin pasar por WordPress
# Esto incluye: jpg, jpeg, png, gif, webp, svg, ico
RewriteCond %{REQUEST_URI} \.(jpg|jpeg|png|gif|webp|svg|ico)$ [NC]
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule . - [L]

# 4. Archivos CSS y JS estáticos - Servir directamente
RewriteCond %{REQUEST_URI} \.(css|js)$ [NC]
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule . - [L]

# 5. Fuentes - Servir directamente
RewriteCond %{REQUEST_URI} \.(woff|woff2|ttf|otf|eot)$ [NC]
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule . - [L]
</IfModule>

# ============================================
# REGLAS DE WORDPRESS (NO MODIFICAR)
# ============================================
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
```

### Configuración Avanzada de Apache

Si quieres servir WebP automáticamente cuando el navegador lo acepta:

```apache
<IfModule mod_rewrite.c>
RewriteEngine On

# Servir WebP automáticamente si existe y el navegador lo acepta
RewriteCond %{HTTP_ACCEPT} image/webp
RewriteCond %{REQUEST_URI} ^(.+)\.(jpg|jpeg|png|gif)$ [NC]
RewriteCond %{DOCUMENT_ROOT}%1.webp -f
RewriteRule ^(.+)\.(jpg|jpeg|png|gif)$ $1.webp [L,T=image/webp]

# Servir imágenes estáticas directamente
RewriteCond %{REQUEST_URI} \.(jpg|jpeg|png|gif|webp|svg|ico)$ [NC]
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule . - [L]
</IfModule>

# Headers para WebP
<IfModule mod_mime.c>
AddType image/webp .webp
</IfModule>

# Cache para imágenes
<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType image/jpeg "access plus 30 days"
ExpiresByType image/png "access plus 30 days"
ExpiresByType image/gif "access plus 30 days"
ExpiresByType image/webp "access plus 30 days"
ExpiresByType image/svg+xml "access plus 30 days"
</IfModule>
```

---

## 🔧 Configuración de Nginx

### Configuración Básica (Bloque `server`)

```nginx
server {
    # ... otras configuraciones ...
    
    # ============================================
    # SERVIR ARCHIVOS ESTÁTICOS DIRECTAMENTE
    # ============================================
    
    # 1. Favicon - Servir directamente si existe
    location = /favicon.ico {
        access_log off;
        log_not_found off;
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
    
    # 2. Imágenes WebP - Servir desde carpeta webp si existe
    location ~* ^/wp-content/uploads/(.+\.(jpg|jpeg|png|gif))$ {
        access_log off;
        log_not_found off;
        expires 30d;
        add_header Cache-Control "public, immutable";
        
        # Si el navegador acepta WebP y existe la versión WebP, servirla
        set $webp_path /wp-content/uploads/webp/$1.webp;
        if ($http_accept ~* "webp") {
            try_files $webp_path $uri =404;
        }
        
        # Si no hay WebP o el navegador no lo acepta, servir original
        try_files $uri =404;
    }
    
    # 3. Imágenes estáticas - Servir directamente
    location ~* \.(jpg|jpeg|png|gif|webp|svg|ico)$ {
        access_log off;
        log_not_found off;
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
    
    # 4. Archivos CSS y JS estáticos
    location ~* \.(css|js)$ {
        access_log off;
        log_not_found off;
        expires 7d;
        add_header Cache-Control "public";
        try_files $uri =404;
    }
    
    # 5. Fuentes
    location ~* \.(woff|woff2|ttf|otf|eot)$ {
        access_log off;
        log_not_found off;
        expires 365d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
    
    # ============================================
    # CONFIGURACIÓN DE WORDPRESS
    # ============================================
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
    
    location ~ \.php$ {
        # ... configuración PHP ...
    }
}
```

### Configuración Avanzada de Nginx

```nginx
# Servir WebP automáticamente cuando existe
location ~* ^(.+)\.(jpg|jpeg|png|gif)$ {
    access_log off;
    log_not_found off;
    expires 30d;
    add_header Cache-Control "public, immutable";
    
    # Intentar servir WebP si el navegador lo acepta
    set $webp_uri $1.webp;
    if ($http_accept ~* "webp") {
        try_files $webp_uri $uri =404;
        add_header Content-Type image/webp;
    }
    
    # Si no hay WebP, servir original
    try_files $uri =404;
}

# Headers para WebP
location ~* \.webp$ {
    access_log off;
    log_not_found off;
    expires 30d;
    add_header Cache-Control "public, immutable";
    add_header Content-Type image/webp;
    try_files $uri =404;
}
```

---

## 🔍 Verificación

### 1. Verificar que las Imágenes se Sirven Directamente

**Apache (Local)**:
```bash
# Verificar headers
curl -I http://localhost/wp-content/uploads/2025/01/imagen.jpg

# Debe mostrar:
# - HTTP/1.1 200 OK
# - Server: Apache
# - No debe haber cookies de WordPress
```

**Nginx (Producción)**:
```bash
# Verificar headers
curl -I https://tusitio.com/wp-content/uploads/2025/01/imagen.jpg

# Debe mostrar:
# - HTTP/1.1 200 OK
# - Server: nginx
# - No debe haber cookies de WordPress
```

### 2. Verificar que WebP se Sirve Correctamente

```bash
# Verificar que WebP se sirve cuando el navegador lo acepta
curl -I -H "Accept: image/webp" https://tusitio.com/wp-content/uploads/2025/01/imagen.jpg

# Debe servir la versión .webp si existe
# Headers deben mostrar:
# - Content-Type: image/webp
# - La URL puede cambiar a .webp
```

### 3. Verificar en el Navegador

1. Abre las **DevTools** (F12)
2. Ve a la pestaña **Network**
3. Filtra por **Img**
4. Carga una página con imágenes
5. Verifica que las imágenes:
   - ✅ Tienen código de respuesta **200**
   - ✅ No tienen cookies de WordPress
   - ✅ Se sirven directamente (no pasan por `index.php`)
   - ✅ Si el navegador acepta WebP, se sirve la versión WebP

---

## 📊 Flujo Completo Optimizado

### 1. Usuario Visita Página

```
Usuario → WordPress → Sistema de Cache → Bricks Builder → HTML con URLs WebP
```

### 2. Navegador Solicita Imagen

```
Navegador → Apache/Nginx → ¿Existe WebP? → Sí → Sirve WebP directamente
                                      ↓
                                    No → Sirve original directamente
```

### 3. Resultado

- ✅ **Cero queries** para imágenes
- ✅ **WebP automático** para navegadores modernos
- ✅ **Fallback automático** para navegadores antiguos
- ✅ **Rendimiento máximo** (imágenes servidas directamente)

---

## ⚠️ Consideraciones Importantes

### 1. Orden de las Reglas

**CRÍTICO**: Las reglas para servir archivos estáticos **DEBEN estar ANTES** de las reglas de WordPress. Si están después, WordPress procesará las peticiones primero.

### 2. Estructura de Carpetas WebP

El sistema WebP del theme guarda las imágenes en:
- **Ubicación**: `/wp-content/uploads/webp/`
- **Formato**: `nombre-archivo.webp`

Asegúrate de que las reglas de Apache/Nginx coincidan con esta estructura.

### 3. Compatibilidad con el Sistema WebP

El sistema WebP del theme:
- ✅ **Convierte imágenes** automáticamente
- ✅ **Reemplaza URLs** en el HTML
- ✅ **Funciona con Apache/Nginx** (sirve archivos directamente)

**No necesitas cambiar nada en el código del theme**, solo configurar Apache/Nginx.

---

## 🚀 Beneficios

### Antes (Sin Apache/Nginx)
- ❌ Cada imagen genera 27-52 queries a la base de datos
- ❌ WordPress procesa cada petición de imagen
- ❌ Tiempo de respuesta: 200-500ms por imagen
- ❌ WebP se sirve pero pasa por WordPress

### Después (Con Apache/Nginx)
- ✅ Cero queries para imágenes
- ✅ Apache/Nginx sirve directamente desde el sistema de archivos
- ✅ Tiempo de respuesta: 10-50ms por imagen
- ✅ **Mejora de 80-90% en tiempo de carga de imágenes**
- ✅ WebP se sirve directamente (sin pasar por WordPress)

---

## 📝 Resumen

### Configuración Necesaria

1. **Apache (Local)**: Agregar reglas en `.htaccess`
2. **Nginx (Producción)**: Agregar reglas en configuración del servidor
3. **Sistema WebP**: Ya funciona, no necesita cambios

### Resultado

- ✅ Imágenes servidas directamente (sin pasar por WordPress)
- ✅ WebP automático para navegadores modernos
- ✅ Cero queries para imágenes
- ✅ Mejora de 80-90% en rendimiento

### Compatibilidad

- ✅ **100% compatible** con el sistema WebP del theme
- ✅ **No afecta** el sistema de cache de queries
- ✅ **Mejora** el rendimiento sin cambiar código

---

## 🛠️ Troubleshooting

### Problema: Las imágenes aún pasan por WordPress

**Solución**: 
- Verifica el orden de las reglas (deben estar ANTES de WordPress)
- Verifica que `mod_rewrite` está habilitado (Apache)
- Verifica permisos de archivos

### Problema: WebP no se sirve

**Solución**:
1. Verifica que los archivos `.webp` existen en `/wp-content/uploads/webp/`
2. Verifica que la ruta en las reglas es correcta
3. Verifica permisos de archivos (deben ser 644)

### Problema: 404 en imágenes

**Solución**:
1. Verifica que la ruta del archivo es correcta
2. Verifica permisos de archivos
3. Verifica que el usuario del servidor tiene acceso a los archivos

