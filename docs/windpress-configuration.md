# Configuración WindPress para SNN-BRX-WIL

## 🎯 **Objetivo**

Configurar WindPress para que Tailwind v4 tenga la especificidad correcta sobre WordPress/Gutenberg/Bricks, optimizando Core Web Vitals.

## 📁 **Archivos de Configuración**

### **1. main.css en WindPress**
```css
/*
 * WindPress Configuration for SNN-BRX-WIL
 * 
 * Este archivo debe ser copiado a WindPress → Files → main.css
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

/* ========================================
   DECLARACIÓN DE CAPAS CSS
   ======================================== */
/* 
 * Estructura simplificada según Flowtitude:
 * https://flowtitude.com/layer-css-wordpress-tailwind/
 * 
 * Orden de prioridad (de menor a mayor):
 * - base: reset/preflight, tipografía global
 * - components: patrones reutilizables
 * - utilities: helpers específicos
 * - custom: overrides para terceros (Bricks, Gutenberg, plugins)
 * 
 * A igualdad de especificidad, gana la capa declarada más tarde.
 * Por lo tanto, 'custom' siempre gana sobre las demás.
 */
@layer base, components, utilities, custom;

/* ========================================
   IMPORTACIÓN DE TAILWIND CSS
   ======================================== */
/* Opción A: Import único (recomendada) */
@import "tailwindcss";

/* 
 * Opción B: Control por capas (avanzada)
 * Descomenta si necesitas control fino:
 * 
 * @import "tailwindcss/preflight" layer(base);
 * @import "tailwindcss/utilities" layer(utilities);
 */

/* ========================================
   IMPORTACIÓN DE ARCHIVOS LOCALES
   ======================================== */
@import "./animaciones.css";
@import "./utilidades.css";
@import "./wizard.css";
@import "./wil.css";
```

### **2. wil.css en WindPress**
```css
/*
 * SNN-BRX-WIL Overrides para WindPress
 * 
 * Este archivo debe ser copiado a WindPress → Files → wil.css
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

@layer custom {
  /* ========================================
     OVERRIDES PARA GUTENBERG
     ======================================== */
  .wp-block-button__link {
    @apply bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700;
  }

  .wp-block-file__button {
    @apply bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700;
  }

  /* ========================================
     OVERRIDES PARA WORDPRESS ADMIN
     ======================================== */
  .admin-bar {
    z-index: 999999 !important;
  }

  /* ========================================
     OVERRIDES PARA BRICKS BUILDER
     ======================================== */
  .brxe-button {
    @apply inline-flex items-center justify-center px-4 py-2 rounded font-medium;
  }

  /* ========================================
     OVERRIDES PARA PLUGINS COMUNES
     ======================================== */
  .elementor-button {
    @apply bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700;
  }

  .wpcf7-form input[type="submit"] {
    @apply bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700;
  }

  .comment-form input[type="submit"] {
    @apply bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700;
  }
}
```

## 🚀 **Pasos de Implementación**

### **Paso 1: Configurar WindPress**
1. Ir a **WindPress → Files**
2. Copiar contenido de `main.css` (arriba)
3. Copiar contenido de `wil.css` (arriba)
4. Guardar cambios

### **Paso 2: Verificar Especificidad**
1. Abrir DevTools
2. Inspeccionar elementos de Gutenberg/Bricks
3. Confirmar que Tailwind tiene prioridad
4. Verificar que no hay conflictos

### **Paso 3: Medir Core Web Vitals**
1. Usar PageSpeed Insights
2. Verificar LCP, FID, CLS
3. Confirmar mejoras en rendimiento

## 📈 **Beneficios Esperados**

### **Core Web Vitals**
- ✅ **LCP**: Mejor carga de CSS
- ✅ **FID**: Menos JavaScript bloqueante
- ✅ **CLS**: Estilos más estables

### **Rendimiento**
- ✅ **Menos CSS**: Solo lo necesario
- ✅ **Mejor caché**: Archivos optimizados
- ✅ **Menos conflictos**: Especificidad clara

### **Mantenibilidad**
- ✅ **Separación clara**: WindPress vs Tema
- ✅ **Fácil debugging**: Estructura simple
- ✅ **Escalabilidad**: Fácil agregar estilos

## 🔧 **Troubleshooting**

### **Problema: Tailwind no tiene prioridad**
**Solución**: Verificar que `wil.css` esté en la capa `custom` (última)

### **Problema: Estilos del tema no se aplican**
**Solución**: Verificar que `snn-theme-specific.css` se carga después de Bricks

### **Problema: Conflictos en admin**
**Solución**: Usar `!important` solo cuando sea necesario

---

**Conclusión**: Esta configuración optimiza Core Web Vitals manteniendo la funcionalidad completa de Tailwind v4 en Bricks Builder.
