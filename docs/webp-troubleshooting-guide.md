# Guía de Troubleshooting: WebP No Se Está Sirviendo

## 🔍 Problema Identificado

Las imágenes WebP no se están sirviendo en el frontend, aunque el sistema de conversión está activo.

---

## ✅ Cambios Realizados

### 1. **NO Eliminar Archivo Original** (CRÍTICO)

**Problema anterior:**
- El sistema eliminaba el archivo original después de convertir a WebP
- Esto impedía que WordPress generara tamaños (thumbnail, medium, large, etc.)
- Sin tamaños, no hay srcset, y las imágenes no se optimizan correctamente

**Solución implementada:**
- ✅ El archivo original se mantiene
- ✅ WebP se crea junto al original (misma carpeta)
- ✅ WordPress puede generar todos los tamaños
- ✅ El sistema usa WebP para servir, pero mantiene original como respaldo

**Archivo modificado:** `includes/webp-image-optimizer.php` (línea ~653)

### 2. **Mejor Detección de URLs en Output Buffer**

**Problema anterior:**
- Solo detectaba URLs con `upload_baseurl` o `/fotoedicion/`
- Podía fallar con URLs relativas o variantes

**Solución implementada:**
- ✅ Detecta múltiples formatos de URL:
  - URLs completas con `upload_baseurl`
  - URLs con `/wp-content/uploads/`
  - URLs con `/fotoedicion/`
  - Rutas relativas que empiezan con `uploads/` o `fotoedicion/`

**Archivo modificado:** `includes/webp-image-optimizer.php` (línea ~1731)

### 3. **Output Buffer Mejorado**

**Problema anterior:**
- Output buffer en `template_redirect` podía no ejecutarse a tiempo
- Conflictos con otros plugins que usan output buffering

**Solución implementada:**
- ✅ Cambiado a `init` hook con prioridad 9999
- ✅ Verificación de output buffers existentes
- ✅ Fallback si ya hay un output buffer activo

**Archivo modificado:** `includes/webp-image-optimizer.php` (línea ~66)

---

## 🔍 Verificación Paso a Paso

### Paso 1: Verificar que las Imágenes WebP Existen

**En el servidor:**
```bash
# Buscar imágenes WebP en uploads
find wp-content/uploads -name "*.webp" | head -10

# Verificar una imagen específica
ls -lh wp-content/uploads/fotoedicion/2025/11/Detenido.webp
```

**En WordPress Admin:**
1. Ve a **SNN Settings → WebP Images**
2. Revisa las estadísticas:
   - Total Images
   - WebP Images
   - Pending Conversion

### Paso 2: Verificar que el Sistema Está Activo

**En WordPress Admin:**
1. Ve a **SNN Settings → WebP Images**
2. Verifica que **"Enable WebP Conversion"** esté activado
3. Verifica que **"WebP Quality"** esté configurado (recomendado: 80)

### Paso 3: Verificar en el HTML del Frontend

**Método 1: Ver Código Fuente**
1. Abre cualquier página en el navegador
2. Click derecho → "Ver código fuente" (Ctrl+U)
3. Busca imágenes (Ctrl+F → busca "jpg" o "webp")
4. **Deberías ver**: URLs con extensión `.webp`

**Ejemplo esperado:**
```html
<!-- ✅ Correcto: WebP en el HTML -->
<img src="https://lanacion.test/wp-content/uploads/fotoedicion/2025/11/Detenido.webp" alt="...">

<!-- ❌ Incorrecto: JPG en el HTML -->
<img src="https://lanacion.test/wp-content/uploads/fotoedicion/2025/11/Detenido.jpeg" alt="...">
```

**Método 2: DevTools Network Tab**
1. Abre DevTools (F12)
2. Ve a la pestaña **Network**
3. Filtra por **Img**
4. Recarga la página
5. **Deberías ver**: Las imágenes solicitadas tienen extensión `.webp`

### Paso 4: Verificar Output Buffer

**Agregar debug temporal (solo para testing):**

Agrega esto temporalmente en `includes/webp-image-optimizer.php` en la función `replace_urls_in_output()`:

```php
// TEMPORAL: Debug
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('WebP Output Buffer: Procesando HTML - Tamaño: ' . strlen($buffer) . ' bytes');
    error_log('WebP Output Buffer: Buscando URLs de imágenes...');
}
```

Luego revisa `wp-content/debug.log` para ver si el output buffer se está ejecutando.

---

## 🚨 Problemas Comunes y Soluciones

### Problema 1: Las Imágenes WebP No Existen

**Síntomas:**
- En el HTML se ven URLs `.jpg`
- No hay archivos `.webp` en el servidor

**Solución:**
1. Ve a **SNN Settings → WebP Images**
2. Haz clic en **"Convertir Imágenes Pendientes"**
3. O usa **"Convertir Imágenes de los 100 Últimos Posts"**

### Problema 2: Output Buffer No Se Ejecuta

**Síntomas:**
- Las imágenes WebP existen
- Pero el HTML sigue mostrando `.jpg`

**Posibles causas:**
1. Otro plugin está usando output buffer y lo está limpiando antes
2. El hook `init` no se está ejecutando
3. Hay un error en el código que detiene la ejecución

**Solución:**
1. Desactiva otros plugins temporalmente para probar
2. Verifica que no haya errores en `wp-content/debug.log`
3. Agrega el debug temporal mencionado arriba

### Problema 3: Algunas Imágenes Usan WebP y Otras No

**Síntomas:**
- Algunas imágenes muestran `.webp`
- Otras muestran `.jpg`

**Posibles causas:**
1. No todas las imágenes han sido convertidas
2. Algunas imágenes están fuera de la carpeta de uploads
3. El output buffer no está capturando todas las URLs

**Solución:**
1. Convierte todas las imágenes pendientes
2. Verifica que todas las imágenes estén en `/wp-content/uploads/` o `/fotoedicion/`
3. Revisa el output buffer para ver si está capturando todas las URLs

### Problema 4: Las Imágenes WebP No Se Cargan (404)

**Síntomas:**
- El HTML muestra URLs `.webp`
- Pero el navegador recibe error 404

**Posibles causas:**
1. Los archivos WebP no existen en la ruta esperada
2. Problemas de permisos
3. Configuración incorrecta de Apache/Nginx

**Solución:**
1. Verifica que los archivos `.webp` existan en el servidor
2. Verifica permisos (deben ser 644)
3. Revisa la configuración de Apache/Nginx (ver `docs/apache-nginx-image-serving-configuration.md`)

---

## 🔧 Mejoras Adicionales Recomendadas

### 1. Agregar Logging de Debug (Opcional)

Para facilitar el troubleshooting, puedes agregar logging temporal:

```php
// En replace_urls_in_output(), después de obtener webp_url
if (defined('WP_DEBUG') && WP_DEBUG && defined('SNN_WEBP_DEBUG') && SNN_WEBP_DEBUG) {
    if ($webp_url && $webp_url !== $image_url) {
        error_log(sprintf('WebP Replacement: %s → %s', basename($image_url), basename($webp_url)));
    } elseif (!$webp_url) {
        error_log(sprintf('WebP Not Found: %s', basename($image_url)));
    }
}
```

Luego en `wp-config.php`:
```php
define('SNN_WEBP_DEBUG', true);
```

### 2. Verificar Orden de Hooks

Si otros plugins usan output buffer, puede haber conflictos. Verifica el orden:

```php
// En functions.php, verificar qué plugins usan output buffer
add_action('init', function() {
    if (ob_get_level() > 0) {
        error_log('Output buffer activo en init - Nivel: ' . ob_get_level());
    }
}, 10000);
```

### 3. Forzar Reemplazo en Todos los Filtros

Asegúrate de que todos los filtros estén activos:

```php
// Verificar que los filtros estén registrados
add_action('wp_footer', function() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        global $wp_filter;
        $filters = array(
            'wp_get_attachment_image_src',
            'wp_get_attachment_image_url',
            'the_post_thumbnail_url',
            'wp_calculate_image_srcset'
        );
        foreach ($filters as $filter) {
            if (isset($wp_filter[$filter])) {
                error_log("Filter $filter está registrado");
            } else {
                error_log("⚠️ Filter $filter NO está registrado");
            }
        }
    }
});
```

---

## 📊 Checklist de Verificación

Usa este checklist para verificar que todo funciona:

- [ ] **Imágenes WebP existen en el servidor**
  - [ ] Verificado con `find` o `ls`
  - [ ] Al menos algunas imágenes tienen versión `.webp`

- [ ] **Sistema WebP está activado**
  - [ ] "Enable WebP Conversion" está activado
  - [ ] "WebP Quality" está configurado

- [ ] **Output Buffer está funcionando**
  - [ ] HTML contiene URLs `.webp` (ver código fuente)
  - [ ] Network tab muestra peticiones `.webp`

- [ ] **Filtros de WordPress están funcionando**
  - [ ] `wp_get_attachment_image_src` reemplaza URLs
  - [ ] `the_post_thumbnail_url` reemplaza URLs
  - [ ] `wp_calculate_image_srcset` reemplaza URLs

- [ ] **No hay conflictos**
  - [ ] No hay errores en `debug.log`
  - [ ] Otros plugins no interfieren

---

## 🎯 Próximos Pasos

1. **Verificar que las imágenes WebP existen**
   - Si no existen, convertirlas usando el botón en admin

2. **Verificar el HTML del frontend**
   - Ver código fuente y buscar URLs `.webp`

3. **Si aún no funciona:**
   - Activar debug temporal
   - Revisar `debug.log`
   - Verificar que no haya conflictos con otros plugins

4. **Una vez funcionando:**
   - Optimizar todas las imágenes existentes
   - Verificar resultados en Lighthouse

---

## 📚 Referencias

- `docs/webp-html-replacement-explanation.md` - Explicación detallada del sistema
- `docs/apache-nginx-image-serving-configuration.md` - Configuración del servidor
- `docs/image-delivery-optimization.md` - Optimización general de imágenes


