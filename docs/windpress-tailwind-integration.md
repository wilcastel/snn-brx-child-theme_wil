# Integración WindPress + Tailwind v4 + @layer

## Contexto
El tema SNN-BRX-WIL es hijo de Bricks Builder y utiliza WindPress para integrar Tailwind v4. Según [Flowtitude](https://flowtitude.com/layer-css-wordpress-tailwind), es crucial implementar una arquitectura de capas CSS para evitar colisiones en el constructor.

## Problema Actual
- Estilos de WordPress/Gutenberg pueden "ganar" sobre nuestros estilos
- CSS inline de terceros interfiere con la cascada
- Falta de control sobre el orden de carga
- Posibles conflictos entre Bricks, WindPress y elementos personalizados

## Solución: Arquitectura de Capas

### Estructura de Capas Recomendada
```css
@layer base, components, utilities, custom;
```

### Implementación con WindPress

#### Opción A - Import Único (Rápida)
```css
@import "tailwindcss";

/* Declarar orden de capas después del import */
@layer base, components, utilities, custom;
```

#### Opción B - Control por Capas (Fina)
```css
/* Declarar orden primero */
@layer base, components, utilities, custom;

/* Importar Tailwind en capas específicas */
@import "tailwindcss/preflight" layer(base);
@import "tailwindcss/utilities" layer(utilities);
```

### Configuración de Tokens
```css
@theme {
  --font-sans: ui-sans-serif, system-ui, "Helvetica Neue", Arial, sans-serif;
  --color-primary: #1e40af;
  --radius-md: .5rem;
  --space-2: .5rem;
}
```

## Implementación en SNN-BRX-WIL

### Ubicación de Archivos
- **WindPress → Files → `main.css`**: Archivo principal de Tailwind
- **Tema → `assets/css/`**: Estilos específicos del tema

### Estructura Propuesta
```css
/* En main.css de WindPress */
@theme {
  /* Tokens del tema SNN */
  --snn-primary: #0073aa;
  --snn-secondary: #29903b;
  --snn-font-family: 'Inter', sans-serif;
}

@layer base, components, utilities, custom;

@import "tailwindcss";

/* En assets/css/snn-theme.css */
@layer base {
  /* Reset específico del tema */
  html { 
    font-family: var(--snn-font-family); 
  }
  
  /* Normalizaciones para Bricks */
  .bricks-element {
    box-sizing: border-box;
  }
}

@layer components {
  /* Componentes reutilizables */
  .snn-btn { 
    @apply inline-flex items-center justify-center px-4 py-2 rounded font-medium transition; 
  }
  
  .snn-btn-primary { 
    @apply bg-[var(--snn-primary)] text-white hover:opacity-90; 
  }
  
  .snn-card {
    @apply bg-white rounded-lg shadow-md p-6;
  }
}

@layer utilities {
  /* Utilidades específicas del tema */
  .snn-text-balance { 
    text-wrap: balance; 
  }
  
  .snn-scroll-smooth {
    scroll-behavior: smooth;
  }
}

@layer custom {
  /* Solo para casos puntuales con terceros */
  /* Documentar cada override */
}
```

## Optimizaciones Específicas para WordPress

### Limitar CSS Inline
```php
// En functions.php o mu-plugin
add_filter('styles_inline_size_limit', function () { 
    return 0; // Desactiva inline y fuerza archivo
});
```

### Cargar Estilos de Bloques por Demanda
```php
add_filter('should_load_separate_core_block_assets', '__return_true');
```

### Remover Global Styles si no se usan
```php
add_action('init', function () {
    remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
    remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
});
```

## Gestión de Dashicons

### Carga Condicional
```php
// Solo cargar Dashicons para usuarios con permisos de editor+
function snn_conditional_dashicons() {
    if (is_user_logged_in() && current_user_can('edit_posts')) {
        wp_enqueue_style('dashicons');
    }
}
add_action('wp_enqueue_scripts', 'snn_conditional_dashicons');
```

## Integración con Elementos Bricks

### Clases CSS en Elementos
```php
// En elementos personalizados
public function render() {
    echo '<div class="snn-element-wrapper">';
    // Contenido del elemento
    echo '</div>';
}
```

### Estilos Específicos de Elementos
```css
@layer components {
    .snn-element-wrapper {
        @apply relative;
    }
    
    .snn-gsap-animations-wrapper {
        @apply overflow-hidden;
    }
}
```

## Checklist de Implementación

- [ ] Configurar estructura de capas en WindPress
- [ ] Definir tokens del tema en `@theme`
- [ ] Implementar carga condicional de Dashicons
- [ ] Limitar CSS inline de WordPress
- [ ] Separar assets de bloques por demanda
- [ ] Documentar cada override en `@layer custom`
- [ ] Probar en constructor Bricks
- [ ] Validar Core Web Vitals

## Beneficios Esperados

1. **Cascada Predecible**: Control total sobre el orden de estilos
2. **Menos Conflictos**: Reducción de problemas con Gutenberg/plugins
3. **Mejor Rendimiento**: Menos CSS innecesario
4. **Mantenibilidad**: Estructura clara y documentada
5. **Escalabilidad**: Fácil añadir nuevos componentes

## Próximos Pasos

1. Auditar CSS actual del tema
2. Implementar estructura de capas
3. Migrar estilos existentes a capas apropiadas
4. Configurar optimizaciones de WordPress
5. Testing exhaustivo en Bricks Builder
