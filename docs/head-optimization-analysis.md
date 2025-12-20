# Análisis de Duplicaciones en el Head - La Nación

## Problemas Identificados

### 1. **CSS Duplicado**
- **style.css cargado 2 veces**:
  - `<link rel="preload" href="...style.css" as="style" onload="...">` (línea 7)
  - `<link rel='stylesheet' id='bricks-child-css' href='...style.css' ...>` (línea 15)

### 2. **Elementos Innecesarios para Usuarios Logueados**
- **Dashicons cargado globalmente** (línea 8):
  - `<link rel='stylesheet' id='dashicons-css' href='...dashicons.min.css' ...>`
  - Debería cargarse solo cuando sea necesario

### 3. **CSS Inline Excesivo**
- **Admin bar CSS inline** (líneas 9-13): 4 líneas de CSS inline
- **Classic theme styles inline** (líneas 14-16): CSS de bloques de WordPress
- **Bricks frontend inline CSS** (líneas 17-19): Fuentes personalizadas
- **Bricks frontend inline CSS masivo** (líneas 20-21): Variables CSS de Tailwind (muy extenso)

### 4. **Enlaces de API y Discovery Innecesarios**
- **JSON API links** (líneas 22-23):
  - `<link rel="https://api.w.org/" href="...wp-json/" />`
  - `<link rel="alternate" title="JSON" type="application/json" href="...wp-json/wp/v2/pages/2" />`
- **Shortlink** (línea 24): `<link rel='shortlink' href='...' />`
- **OEmbed links** (líneas 25-26): Enlaces de discovery de OEmbed

### 5. **Scripts de WindPress**
- **Metadata script** (línea 30): Script con configuración de WindPress
- **VFS script** (línea 30): Script con contenido CSS (muy extenso)

## Optimizaciones Necesarias

### 1. **Eliminar CSS Duplicado**
```php
// Evitar carga doble de style.css
function snn_prevent_duplicate_css() {
    // Remover preload si ya se carga como stylesheet
    remove_action('wp_head', 'wp_enqueue_global_styles');
}
```

### 2. **Carga Condicional de Dashicons**
```php
// Solo cargar Dashicons cuando sea necesario
function snn_conditional_dashicons() {
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_dequeue_style('dashicons');
    }
}
```

### 3. **Limitar CSS Inline**
```php
// Limitar CSS inline de WordPress
add_filter('styles_inline_size_limit', function() {
    return 0; // Forzar archivos externos
});
```

### 4. **Remover Enlaces Innecesarios**
```php
// Remover enlaces de API y discovery
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
```

### 5. **Optimizar WindPress**
```php
// Optimizar scripts de WindPress
add_filter('windpress_debug_mode', '__return_false');
```

## Implementación de Soluciones

Voy a actualizar el archivo `security-optimization.php` para incluir estas optimizaciones específicas.
