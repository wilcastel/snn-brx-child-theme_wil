# Implementación de @layer para WindPress + Tailwind v4

## 🎯 Objetivo

Implementar una arquitectura de capas CSS (`@layer`) siguiendo las mejores prácticas de [Flowtitude](https://flowtitude.com/layer-css-wordpress-tailwind/) para garantizar que **WindPress/Tailwind siempre tenga prioridad sobre Bricks Builder**.

---

## 📚 Referencia

Este documento se basa en el artículo: **[Introducción a las @layer en CSS: Mejora la organización y control de estilos con WordPress](https://flowtitude.com/layer-css-wordpress-tailwind/)**

---

## 🔍 Problema Actual

En WordPress, los estilos llegan desde múltiples orígenes:
- **WordPress Core** (Gutenberg)
- **Bricks Builder** (constructor visual)
- **WindPress/Tailwind v4** (nuestro sistema de estilos)
- **Plugins** (estilos adicionales)

**Sin `@layer`**, la cascada decide por ti, y normalmente gana quien carga más tarde o quien mete CSS **sin capa** (o inline). Esto causa conflictos donde Bricks puede "ganar" sobre Tailwind.

---

## ✅ Solución: Arquitectura de Capas Simple

### Estructura Recomendada

**Menos es más.** El artículo recomienda mantener pocas capas con responsabilidades claras:

```css
@layer base, components, utilities, custom;
```

**Orden de prioridad** (de menor a mayor):
1. **`base`** → reset/preflight, tipografía global, normalizaciones
2. **`components`** → patrones reutilizables (.btn, .card, .input…)
3. **`utilities`** → pequeños helpers cuando no exista la utilidad Tailwind
4. **`custom`** → último recurso para convivir con CSS de terceros (Bricks, Gutenberg, plugins)

**Regla clave**: A igualdad de especificidad, **gana la capa declarada más tarde**. Por lo tanto, `custom` siempre gana sobre las demás.

---

## 🚀 Implementación en WindPress

### Ubicación del Archivo

**WindPress → Files → `main.css`**

Este es el archivo principal donde configuramos Tailwind v4 y las capas CSS.

---

## 📝 Opción A: Import Único (Rápida) - RECOMENDADA

Esta es la opción más simple y recomendada para empezar:

```css
/*
 * WindPress Configuration for SNN-BRX-WIL
 * Basado en: https://flowtitude.com/layer-css-wordpress-tailwind/
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

/* ========================================
   TOKENS DEL TEMA (Opcional, Recomendado)
   ======================================== */
@theme {
  --font-sans: ui-sans-serif, system-ui, "Helvetica Neue", Arial, sans-serif;
  --color-primary: #1e40af;
  --color-secondary: #29903b;
  --radius-md: .5rem;
  --space-2: .5rem;
}

/* ========================================
   IMPORTACIÓN DE TAILWIND CSS
   ======================================== */
@import "tailwindcss";

/* ========================================
   DECLARACIÓN DE CAPAS (Después del import)
   ======================================== */
@layer base, components, utilities, custom;

/* ========================================
   CAPA BASE
   ======================================== */
@layer base {
  /* Reset específico del tema */
  html { 
    font-family: var(--font-sans, ui-sans-serif); 
  }
  
  a { 
    text-decoration: none; 
  }
  
  /* Normalizaciones para Bricks */
  .bricks-element {
    box-sizing: border-box;
  }
}

/* ========================================
   CAPA COMPONENTS
   ======================================== */
@layer components {
  /* Componentes reutilizables */
  .btn { 
    @apply inline-flex items-center justify-center px-4 py-2 rounded font-medium transition; 
  }
  
  .btn-primary { 
    @apply bg-[var(--color-primary)] text-white hover:opacity-90; 
  }
  
  .input { 
    @apply w-full px-3 py-2 border rounded outline-none; 
  }
  
  .input:focus { 
    @apply ring-2 ring-[var(--color-primary)]; 
  }
  
  .card {
    @apply bg-white rounded-lg shadow-md p-6;
  }
}

/* ========================================
   CAPA UTILITIES
   ======================================== */
@layer utilities {
  /* Utilidades específicas del tema */
  .text-balance { 
    text-wrap: balance; 
  }
  
  .scroll-smooth {
    scroll-behavior: smooth;
  }
}

/* ========================================
   CAPA CUSTOM (Última - Mayor Prioridad)
   ======================================== */
@layer custom {
  /* 
   * SOLO para casos puntuales con terceros (Bricks, Gutenberg, plugins)
   * Documentar cada override explicando POR QUÉ existe
   */
  
  /* Overrides para Bricks Builder */
  .brxe-button {
    @apply inline-flex items-center justify-center px-4 py-2 rounded font-medium;
  }
  
  /* Overrides para Gutenberg */
  .wp-block-button__link {
    @apply bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700;
  }
  
  .wp-block-file__button {
    @apply bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700;
  }
  
  /* Overrides para plugins comunes */
  .elementor-button {
    @apply bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700;
  }
  
  .wpcf7-form input[type="submit"] {
    @apply bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700;
  }
}
```

---

## 📝 Opción B: Control por Capas (Fina) - AVANZADA

Si necesitas control fino sobre qué entra en cada capa:

```css
/*
 * WindPress Configuration for SNN-BRX-WIL
 * Opción B: Control por Capas (Fina)
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

/* ========================================
   TOKENS DEL TEMA
   ======================================== */
@theme {
  --font-sans: ui-sans-serif, system-ui, "Helvetica Neue", Arial, sans-serif;
  --color-primary: #1e40af;
  --radius-md: .5rem;
}

/* ========================================
   DECLARACIÓN DE CAPAS (Primero)
   ======================================== */
@layer base, components, utilities, custom;

/* ========================================
   IMPORTACIÓN DE TAILWIND EN CAPAS ESPECÍFICAS
   ======================================== */
@import "tailwindcss/preflight" layer(base);
@import "tailwindcss/utilities" layer(utilities);

/* ========================================
   TUS COMPONENTES Y UTILIDADES
   ======================================== */
@layer components {
  .btn { 
    @apply inline-flex items-center justify-center px-4 py-2 rounded font-medium; 
  }
}

@layer utilities {
  .text-balance { 
    text-wrap: balance; 
  }
}

@layer custom {
  /* Overrides para terceros */
  .brxe-button {
    @apply inline-flex items-center justify-center px-4 py-2 rounded font-medium;
  }
}
```

**Nota**: La Opción B es útil si tu `base` ya establece tipografía y quieres evitar conflictos. Para la mayoría de casos, la **Opción A es suficiente**.

---

## 🎯 Garantizar que WindPress/Tailwind Gane sobre Bricks

### Estrategia

1. **Bricks carga primero** (sin capa o en capas tempranas)
2. **WindPress/Tailwind carga después** (en capas posteriores, especialmente `custom`)
3. **Estilos del tema en `custom`** (última capa, mayor prioridad)

### Orden de Carga en WordPress

El orden de carga en WordPress es:
1. WordPress Core CSS
2. Plugin CSS (Bricks)
3. Theme CSS (nuestro tema)
4. WindPress CSS (se inyecta vía scripts)

**Con `@layer`**, aunque Bricks cargue antes, si nuestros estilos están en `custom` (última capa), **siempre ganarán** a igualdad de especificidad.

---

## 🔧 Optimizaciones Adicionales para WordPress

### 1. Limitar CSS Inline

Gutenberg a menudo inyecta CSS **inline** o carga estilos **sin capa**, que en igualdad de especificidad **se imponen** sobre tus capas.

**Solución** (en `functions.php` o mu-plugin):

```php
// Limitar CSS inline (0 = desactiva inline y fuerza archivo)
add_filter('styles_inline_size_limit', function () { 
    return 0; 
});
```

### 2. Cargar Estilos de Bloques por Demanda

Reduce CSS global y menos choques:

```php
add_filter('should_load_separate_core_block_assets', '__return_true');
```

### 3. Remover Global Styles si no se usan

```php
add_action('init', function () {
    remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
    remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
});
```

**Nota**: Estas optimizaciones ya están implementadas en `includes/head-optimization.php` y `includes/assets-optimization.php`.

---

## ⚠️ Errores Comunes (y Cómo Evitarlos)

### ❌ Error 1: Mezclar Opción A y B
**Problema**: Capas duplicadas/desorden  
**Solución**: Elige **una** opción (A o B) y manténla consistente

### ❌ Error 2: Encolar antes que Gutenberg/plugins
**Problema**: Pierdes prioridad  
**Solución**: Encola al final o ajusta dependencias en `enqueue-scripts.php`

### ❌ Error 3: Abusar de `custom`
**Problema**: Arquitectura opaca  
**Solución**: Usa `custom` solo tras ordenar encolado/inline. Documenta cada override

### ❌ Error 4: Crear diez capas
**Problema**: Mantenimiento imposible  
**Solución**: Mantén 3-4 capas máximo (`base, components, utilities, custom`)

### ❌ Error 5: Ignorar inline
**Problema**: "Fantasmas" que te pisan  
**Solución**: Limita inline con `styles_inline_size_limit`

### ❌ Error 6: Usar `!important` sistemáticamente
**Problema**: Te encierra  
**Solución**: Arregla orden, no la sintomatología. `!important` solo cuando sea absolutamente necesario

---

## 📋 Checklist de Verificación

- [ ] Declaraste `@layer base, components, utilities, custom;` al inicio
- [ ] Usas **solo una** estrategia de import v4 (A o B)
- [ ] Tu CSS se **encola después** del de plugins/Gutenberg
- [ ] ¿Has activado **assets por demanda** de bloques si te conviene?
- [ ] ¿Has limitado el **inline** de WP si te pisa?
- [ ] ¿`custom` existe pero está acotado y documentado?
- [ ] ¿Tokens en `@theme` y estilos globales en `base`?
- [ ] ¿Probado en plantillas reales y en el editor Bricks?

---

## 🔍 Verificación Práctica

### 1. Verificar que las Capas Están Activas

Abre DevTools (F12) → Elements → Busca un elemento con estilos de Tailwind

**Deberías ver**:
```css
/* En la pestaña Styles */
@layer utilities {
  .bg-blue-500 { ... }
}
```

### 2. Verificar que Tailwind Gana sobre Bricks

1. Inspecciona un botón de Bricks
2. Aplica una clase Tailwind (ej: `bg-red-500`)
3. **Debería aplicarse** (Tailwind gana)

### 3. Verificar Orden de Capas

En DevTools → Sources → Busca `main.css` de WindPress

**Deberías ver**:
```css
@layer base, components, utilities, custom;
```

---

## 📚 Referencias

- [Flowtitude: Introducción a las @layer en CSS](https://flowtitude.com/layer-css-wordpress-tailwind/)
- [MDN: @layer](https://developer.mozilla.org/en-US/docs/Web/CSS/@layer)
- [Tailwind CSS v4: Layers](https://tailwindcss.com/docs/functions-and-directives#layer)

---

## 🎯 Resumen

1. **Estructura simple**: `@layer base, components, utilities, custom;`
2. **Opción A recomendada**: `@import "tailwindcss";` seguido de declaración de capas
3. **`custom` siempre gana**: Última capa, mayor prioridad sobre Bricks/Gutenberg
4. **Limitar inline**: Evitar que CSS inline de WordPress "gane" sobre capas
5. **Documentar overrides**: Cada override en `custom` debe explicar por qué existe

**Con esta configuración, WindPress/Tailwind siempre tendrá prioridad sobre Bricks Builder** a igualdad de especificidad.


