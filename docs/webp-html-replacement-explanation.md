# Cómo Funciona el Reemplazo de URLs WebP en el HTML

## 🎯 Objetivo

El sistema reemplaza automáticamente las URLs de imágenes JPG/PNG por WebP en el HTML generado, **antes de que se envíe al navegador**. Esto asegura que el navegador reciba directamente las URLs WebP optimizadas.

---

## 🔄 Filtros de WordPress Implementados

El sistema intercepta **todos** los puntos donde WordPress genera URLs de imágenes y las reemplaza por WebP:

### 1. `wp_get_attachment_image_src`
**Cuándo se usa**: Imágenes destacadas, thumbnails, imágenes de posts
**Filtro**: `replace_with_webp()`
**Ejemplo**:
```php
// WordPress genera:
wp_get_attachment_image_src($id, 'large')
// → ['https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.jpg', 800, 600]

// Sistema WebP reemplaza:
// → ['https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.webp', 800, 600]
```

### 2. `wp_get_attachment_image_url`
**Cuándo se usa**: URLs directas de imágenes de attachments
**Filtro**: `replace_attachment_image_url()`
**Ejemplo**:
```php
// WordPress genera:
wp_get_attachment_image_url($id, 'full')
// → 'https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.jpg'

// Sistema WebP reemplaza:
// → 'https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.webp'
```

### 3. `wp_get_attachment_url`
**Cuándo se usa**: URLs base de attachments
**Filtro**: `replace_attachment_url_with_webp()`
**Ejemplo**:
```php
// WordPress genera:
wp_get_attachment_url($id)
// → 'https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.jpg'

// Sistema WebP reemplaza:
// → 'https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.webp'
```

### 4. `the_post_thumbnail_url`
**Cuándo se usa**: URLs de imágenes destacadas de posts
**Filtro**: `replace_post_thumbnail_url()`
**Ejemplo**:
```php
// WordPress genera:
the_post_thumbnail_url('large')
// → 'https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.jpg'

// Sistema WebP reemplaza:
// → 'https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.webp'
```

### 5. `the_content`
**Cuándo se usa**: Contenido de posts (imágenes insertadas)
**Filtro**: `replace_content_images()`
**Ejemplo**:
```html
<!-- WordPress genera: -->
<img src="https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.jpg" alt="...">

<!-- Sistema WebP reemplaza: -->
<img src="https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.webp" alt="...">
```

### 6. `wp_calculate_image_srcset`
**Cuándo se usa**: Atributos `srcset` para imágenes responsivas
**Filtro**: `replace_srcset_with_webp()`
**Ejemplo**:
```html
<!-- WordPress genera: -->
<img srcset="imagen-300.jpg 300w, imagen-600.jpg 600w, imagen-1200.jpg 1200w">

<!-- Sistema WebP reemplaza: -->
<img srcset="imagen-300.webp 300w, imagen-600.webp 600w, imagen-1200.webp 1200w">
```

---

## 📋 Flujo Completo

### 1. WordPress Genera HTML

```
WordPress → Genera HTML con URLs JPG/PNG
```

### 2. Filtros Interceptan URLs

```
Filtros → Reemplazan URLs JPG/PNG por WebP (si existe)
```

### 3. HTML Final con WebP

```
HTML Final → Contiene URLs WebP directamente
```

### 4. Navegador Recibe HTML

```
Navegador → Recibe HTML con URLs WebP
         → Solicita imágenes WebP directamente
         → Apache/Nginx sirve WebP sin pasar por WordPress
```

---

## ✅ Verificación

### 1. Ver en el Código Fuente

1. Abre cualquier página en el navegador
2. Click derecho → "Ver código fuente" (o Ctrl+U)
3. Busca imágenes (Ctrl+F → "jpg" o "webp")
4. **Deberías ver**: URLs con extensión `.webp`

**Ejemplo**:
```html
<!-- ✅ Correcto: WebP en el HTML -->
<img src="https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.webp" alt="...">

<!-- ❌ Incorrecto: JPG en el HTML -->
<img src="https://site.com/wp-content/uploads/fotoedicion/2025/01/imagen.jpg" alt="...">
```

### 2. Ver en DevTools

1. Abre DevTools (F12)
2. Ve a la pestaña **Network**
3. Filtra por **Img**
4. Carga una página
5. **Deberías ver**: Las imágenes solicitadas tienen extensión `.webp`

### 3. Verificar que Funciona

**Si ves WebP en el HTML**:
- ✅ El sistema está funcionando correctamente
- ✅ Las URLs se están reemplazando antes de enviar al navegador

**Si ves JPG en el HTML**:
- ⚠️ Verifica que las imágenes WebP existan en el servidor
- ⚠️ Verifica que `enable_webp` esté activado en la configuración
- ⚠️ Verifica que no haya conflictos con Cloudflare u otros plugins

---

## 🔍 Dónde Busca el Sistema

El sistema busca las imágenes WebP en la **misma carpeta** que las originales:

```
/wp-content/uploads/fotoedicion/2025/01/
  ├── imagen.jpg          (original)
  └── imagen.webp         (WebP - misma carpeta)
```

**Ventajas**:
- ✅ Respeta estructura de carpetas personalizada
- ✅ Fácil de gestionar (original y WebP juntos)
- ✅ Compatible con Apache/Nginx

---

## ⚙️ Configuración

### Activar/Desactivar

En **WordPress Admin** → **SNN Settings** → **WebP Image Optimization**:
- ✅ **Enable WebP Conversion**: Debe estar activado
- ✅ **WebP Quality**: Calidad de conversión (recomendado: 85)

### Verificar Estado

1. Ve a la página de **WebP Image Optimization**
2. Verifica las estadísticas:
   - Total Images
   - WebP Images
   - Conversion Rate
   - Pending Conversion

---

## 🚨 Troubleshooting

### Problema: Las URLs en el HTML siguen siendo JPG

**Posibles causas**:
1. Las imágenes WebP no existen en el servidor
2. El sistema WebP está desactivado
3. Hay un conflicto con otro plugin

**Solución**:
1. Verifica que las imágenes WebP existan en el servidor
2. Activa "Enable WebP Conversion" en la configuración
3. Convierte las imágenes usando el botón "Convertir Imágenes Pendientes"

### Problema: Algunas imágenes usan WebP y otras no

**Posibles causas**:
1. No todas las imágenes han sido convertidas
2. Algunas imágenes no están en la carpeta de uploads

**Solución**:
1. Usa el botón "Convertir Imágenes Pendientes"
2. O convierte manualmente las imágenes faltantes

### Problema: Las imágenes WebP no se cargan

**Posibles causas**:
1. Los archivos WebP no existen
2. Problemas de permisos
3. Configuración incorrecta de Apache/Nginx

**Solución**:
1. Verifica que los archivos `.webp` existan en el servidor
2. Verifica permisos de archivos (deben ser 644)
3. Revisa la configuración de Apache/Nginx

---

## 📊 Resumen

### ✅ Lo que Hace el Sistema

1. **Intercepta** todas las funciones de WordPress que generan URLs de imágenes
2. **Reemplaza** URLs JPG/PNG por WebP (si existe)
3. **Mantiene** la estructura de carpetas original
4. **Genera** HTML con URLs WebP directamente

### ✅ Resultado

- HTML contiene URLs WebP (no JPG)
- Navegador solicita imágenes WebP directamente
- Apache/Nginx sirve WebP sin pasar por WordPress
- Mejor rendimiento y Core Web Vitals

---

## 💡 Notas Importantes

1. **El reemplazo es automático**: No necesitas cambiar nada en tu código
2. **Respeta estructura de carpetas**: Si usas "fotoedicion", los WebP estarán en "fotoedicion"
3. **Fallback automático**: Si no hay WebP, se usa la imagen original
4. **Compatible con Bricks Builder**: Funciona con todos los elementos de Bricks

El sistema está diseñado para funcionar de forma transparente: simplemente convierte las imágenes y el HTML automáticamente usa las versiones WebP.


