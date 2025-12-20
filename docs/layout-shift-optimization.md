# Optimización de Layout Shifts (CLS)

## 🎯 Problema Identificado por Lighthouse

**Layout shift culprits - CLS Score: 0.085**

### Problema:
Los layout shifts ocurren cuando los elementos se mueven sin interacción del usuario. Esto causa una mala experiencia de usuario y afecta el CLS (Cumulative Layout Shift), una métrica clave de Core Web Vitals.

**CLS Score actual: 0.085** (Límite "bueno": < 0.1, Ideal: < 0.05)

---

## 📊 Análisis de Elementos Problemáticos

### Elemento 1: Grid de Imágenes (Bricks)
**Selector**: `div#brxe-boqtic.brxe-div.flex.flex-wrap.justify-center.gap-4.wrap.mx-auto.p-4.my-5`  
**CLS Score**: 0.085  
**Problema**: Grid de imágenes que se carga dinámicamente sin dimensiones reservadas

### Elemento 2: Imagen Sin Tamaño
**Selector**: `img.css-filter.size-large.snn-responsive-image`  
**Problema**: **Unsized image element** - Imagen sin atributos `width` y `height`

---

## 🔍 Causas Comunes de Layout Shifts

### 1. Imágenes Sin Dimensiones ⚠️ **CRÍTICO**

**Problema**: Las imágenes sin `width` y `height` causan layout shifts cuando se cargan.

**Solución**:
- Agregar atributos `width` y `height` a todas las imágenes
- Usar `aspect-ratio` CSS para mantener proporciones
- Usar placeholders mientras cargan

### 2. Contenido Dinámico

**Problema**: Contenido que se agrega dinámicamente (ads, widgets, etc.) sin reservar espacio.

**Solución**:
- Reservar espacio con min-height o aspect-ratio
- Usar skeletons/placeholders
- Cargar contenido no crítico después del render inicial

### 3. Fuentes Web

**Problema**: Las fuentes web pueden causar FOIT (Flash of Invisible Text) o FOUT (Flash of Unstyled Text).

**Solución**:
- Usar `font-display: swap` (ya implementado)
- Preload fuentes críticas (ya implementado)
- Usar `font-display: optional` para fuentes no críticas

### 4. Ads y Embeds

**Problema**: Los anuncios y embeds se cargan después y desplazan contenido.

**Solución**:
- Reservar espacio con contenedores de tamaño fijo
- Usar lazy loading para ads
- Cargar ads después del render inicial

---

## 🔧 Soluciones Implementadas

### 1. Aspect Ratio para Imágenes ✅

**Archivo**: `includes/webp-image-optimizer.php`

Ya tenemos implementado lazy loading con placeholders SVG, pero necesitamos asegurar que las imágenes tengan dimensiones.

### 2. Font Display Swap ✅

**Archivo**: `includes/assets-optimization.php`

Ya implementado: `font-display: swap` para todas las fuentes.

### 3. Preload de Fuentes Críticas ✅

**Archivo**: `includes/assets-optimization.php`

Ya implementado: Preload de fuentes críticas.

---

## 🚀 Soluciones a Implementar

### 1. Agregar Dimensiones a Imágenes

**Problema**: Las imágenes no tienen atributos `width` y `height`, causando layout shifts.

**Solución**: Agregar filtros para inyectar dimensiones automáticamente.

**Implementación**:
```php
// En includes/webp-image-optimizer.php
add_filter('wp_get_attachment_image_attributes', function($attr, $attachment, $size) {
    // Agregar width y height si no existen
    if (!isset($attr['width']) || !isset($attr['height'])) {
        $image_meta = wp_get_attachment_metadata($attachment->ID);
        if ($image_meta) {
            $attr['width'] = $image_meta['width'];
            $attr['height'] = $image_meta['height'];
            // Agregar aspect-ratio CSS
            $aspect_ratio = $image_meta['width'] / $image_meta['height'];
            $attr['style'] = (isset($attr['style']) ? $attr['style'] . '; ' : '') . 
                           "aspect-ratio: {$aspect_ratio};";
        }
    }
    return $attr;
}, 10, 3);
```

### 2. Aspect Ratio CSS para Contenedores

**Problema**: Grids y contenedores que cambian de tamaño cuando se carga contenido.

**Solución**: Usar `aspect-ratio` CSS para reservar espacio.

**Implementación**:
```css
/* En assets/css/snn-theme-specific.css o en WindPress */
.snn-image-grid {
    aspect-ratio: 16 / 9; /* O la proporción que necesites */
    min-height: 200px; /* Fallback para navegadores antiguos */
}

.snn-responsive-image {
    width: 100%;
    height: auto;
    aspect-ratio: attr(width) / attr(height); /* Si el navegador lo soporta */
}
```

### 3. Reservar Espacio para Contenido Dinámico

**Problema**: Contenido que se carga dinámicamente (grids, ads, etc.) sin reservar espacio.

**Solución**: Agregar min-height o aspect-ratio a contenedores.

**Para el grid de Bricks específico**:
```css
/* En WindPress → Files → wil.css o en snn-theme-specific.css */
#brxe-boqtic.brxe-div {
    min-height: 400px; /* Ajustar según el contenido esperado */
    /* O usar aspect-ratio si conoces la proporción */
    /* aspect-ratio: 16 / 9; */
}
```

### 4. Skeleton/Placeholder para Contenido Dinámico

**Problema**: Contenido que se carga después causa layout shifts.

**Solución**: Mostrar skeleton/placeholder mientras carga.

**Implementación**:
```html
<!-- Placeholder para grid de imágenes -->
<div class="snn-image-grid-placeholder" style="min-height: 400px; background: #f3f4f6;">
    <!-- Skeleton loader -->
</div>
```

---

## 📝 Implementación Detallada

### Paso 1: Agregar Dimensiones a Imágenes

**Archivo**: `includes/webp-image-optimizer.php`

Agregar filtro para inyectar `width`, `height` y `aspect-ratio`:

```php
/**
 * Agregar dimensiones a imágenes para prevenir layout shifts
 */
public function add_image_dimensions($attr, $attachment, $size) {
    // Solo en frontend
    if (is_admin()) {
        return $attr;
    }
    
    // Si ya tiene width y height, no hacer nada
    if (isset($attr['width']) && isset($attr['height'])) {
        // Agregar aspect-ratio CSS si no existe
        if (!isset($attr['style']) || strpos($attr['style'], 'aspect-ratio') === false) {
            $width = (int) $attr['width'];
            $height = (int) $attr['height'];
            if ($width > 0 && $height > 0) {
                $aspect_ratio = $width / $height;
                $attr['style'] = (isset($attr['style']) ? $attr['style'] . '; ' : '') . 
                               "aspect-ratio: {$aspect_ratio};";
            }
        }
        return $attr;
    }
    
    // Obtener dimensiones de la imagen
    $image_meta = wp_get_attachment_metadata($attachment->ID);
    if ($image_meta && isset($image_meta['width']) && isset($image_meta['height'])) {
        $width = $image_meta['width'];
        $height = $image_meta['height'];
        
        // Para tamaños específicos, obtener dimensiones del tamaño
        if ($size && $size !== 'full') {
            $image_src = wp_get_attachment_image_src($attachment->ID, $size);
            if ($image_src) {
                $width = $image_src[1];
                $height = $image_src[2];
            }
        }
        
        // Agregar atributos width y height
        $attr['width'] = $width;
        $attr['height'] = $height;
        
        // Agregar aspect-ratio CSS
        if ($width > 0 && $height > 0) {
            $aspect_ratio = $width / $height;
            $attr['style'] = (isset($attr['style']) ? $attr['style'] . '; ' : '') . 
                           "aspect-ratio: {$aspect_ratio};";
        }
    }
    
    return $attr;
}
```

### Paso 2: Agregar Estilos para Contenedores

**Archivo**: `assets/css/snn-theme-specific.css` o en WindPress `wil.css`

```css
/* Prevenir layout shifts en imágenes */
.snn-responsive-image,
img.size-large,
img.size-medium,
img.size-thumbnail {
    width: 100%;
    height: auto;
    /* aspect-ratio se agregará vía atributo style */
}

/* Reservar espacio para grid de imágenes */
#brxe-boqtic.brxe-div,
.snn-image-grid {
    min-height: 400px; /* Ajustar según contenido */
    /* O usar aspect-ratio si conoces la proporción */
}

/* Placeholder para contenido que carga dinámicamente */
.snn-content-placeholder {
    min-height: 200px;
    background: #f3f4f6;
    border-radius: 4px;
}
```

---

## 🎯 Plan de Acción

### Fase 1: Imágenes Sin Dimensiones (Prioridad Alta)

1. **Agregar filtro para inyectar width/height** ✅
2. **Agregar aspect-ratio CSS** ✅
3. **Verificar que todas las imágenes tienen dimensiones**

### Fase 2: Contenedores Dinámicos (Prioridad Media)

1. **Agregar min-height a grids de Bricks**
2. **Agregar aspect-ratio a contenedores conocidos**
3. **Usar placeholders para contenido dinámico**

### Fase 3: Optimizaciones Adicionales (Prioridad Baja)

1. **Optimizar carga de fuentes** (ya implementado)
2. **Reservar espacio para ads** (si aplica)
3. **Optimizar embeds** (si aplica)

---

## 📈 Resultados Esperados

### Con Dimensiones en Imágenes:
- **Mejora CLS**: ~0.03-0.05
- Imágenes no causan layout shifts
- Mejor experiencia de usuario

### Con Reserva de Espacio para Contenedores:
- **Mejora CLS**: ~0.02-0.03
- Contenido dinámico no desplaza elementos
- Layout más estable

### **Total Esperado**: CLS < 0.05 (Excelente)

---

## ⚠️ Consideraciones Importantes

1. **Aspect Ratio**: No todos los navegadores soportan `aspect-ratio` CSS (requiere fallback con min-height)
2. **Responsive**: Las dimensiones deben ser responsivas
3. **Testing**: Probar en diferentes dispositivos y tamaños de pantalla
4. **Bricks Builder**: Algunos elementos de Bricks pueden requerir ajustes específicos

---

## 🔍 Verificación

### Verificar Dimensiones de Imágenes:
1. Abre DevTools (F12)
2. Elements → Busca imágenes
3. Verifica que tienen atributos `width` y `height`
4. Verifica que tienen `aspect-ratio` en el style

### Verificar CLS:
1. Ejecuta Lighthouse
2. Busca "Layout shift culprits"
3. El CLS debería ser < 0.05 (Excelente)

### Verificar en DevTools:
1. DevTools → Performance → Grabar carga de página
2. Busca "Layout Shift" en la línea de tiempo
3. Identifica qué elementos causan shifts

---

## 📚 Referencias

- [Cumulative Layout Shift (CLS)](https://web.dev/cls/)
- [Optimize Cumulative Layout Shift](https://web.dev/optimize-cls/)
- [Aspect Ratio CSS](https://developer.mozilla.org/en-US/docs/Web/CSS/aspect-ratio)

---

## 💡 Resumen

El problema principal es:
1. **Imágenes sin dimensiones** - Agregar `width`, `height` y `aspect-ratio`
2. **Contenedores dinámicos** - Reservar espacio con `min-height` o `aspect-ratio`

**Prioridad**: Implementar dimensiones en imágenes primero, ya que es la causa más común y fácil de solucionar.


