# Plan de Acción - Fase 1: Seguridad y Optimización del Head

## Objetivo
Implementar un sistema unificado de configuración de seguridad y optimización del head para mejorar Core Web Vitals y seguridad del sitio.

## Análisis Actual

### Estado de Seguridad Existente
Revisar archivos actuales:
- `includes/security-page.php` - Configuraciones básicas
- `includes/disable-xmlrpc.php` - XML-RPC deshabilitado
- `includes/disable-wp-json-if-not-logged-in.php` - JSON API para invitados
- `includes/disable-file-editing.php` - Edición de archivos
- `includes/remove-rss.php` - RSS feeds
- `includes/remove-wp-version.php` - Versión de WP
- `includes/disable-emojis.php` - Emojis
- `includes/disable-gravatar.php` - Gravatar

### Funcionalidades Pendientes
- Limpieza avanzada del head
- Optimización de carga de CSS/JS
- Configuraciones de protocolo
- Gestión inteligente de Dashicons

## Implementación

### Paso 1: Auditoría y Consolidación
1. **Auditar archivos de seguridad existentes**
   - Identificar duplicaciones
   - Documentar funcionalidades actuales
   - Crear lista de mejoras necesarias

2. **Consolidar en un solo archivo**
   - Crear `includes/security-optimization.php`
   - Migrar funcionalidades existentes
   - Mantener compatibilidad hacia atrás

### Paso 2: Nuevas Funcionalidades de Seguridad

#### Limpieza Avanzada del Head
```php
// Funciones a implementar
function snn_remove_wp_generator() { /* Remove WordPress generator meta tag */ }
function snn_remove_wlw_manifest() { /* Remove Windows Live Writer manifest */ }
function snn_remove_rsd_link() { /* Remove Really Simple Discovery link */ }
function snn_remove_shortlink() { /* Remove WordPress shortlink */ }
function snn_remove_wp_json_links() { /* Remove WordPress JSON API links */ }
function snn_remove_oembed_links() { /* Remove OEmbed discovery links */ }
function snn_remove_dns_prefetch() { /* Remove DNS prefetch hints */ }
function snn_remove_emoji_scripts() { /* Remove emoji detection scripts */ }
function snn_remove_wp_block_library() { /* Remove WordPress block library CSS */ }
```

#### Optimización de Carga
```php
// Funciones de optimización
function snn_optimize_css_loading() { /* Optimize CSS loading with font preloading */ }
function snn_preload_critical_fonts() { /* Preload critical fonts */ }
function snn_keep_essential_meta() { /* Keep essential meta tags */ }
function snn_keep_social_meta() { /* Keep social media meta tags */ }
function snn_keep_seo_meta() { /* Keep SEO meta tags */ }
```

#### Configuraciones de Protocolo
```php
// Funciones de protocolo
function snn_force_https() { /* Force redirection to HTTPS */ }
function snn_fix_mixed_content() { /* Fix mixed content URLs */ }
function snn_add_cors_headers() { /* Add CORS headers */ }
function snn_protocol_detection() { /* Add protocol detection in JS */ }
function snn_fix_font_urls() { /* Fix font URLs to prevent CORS errors */ }
```

### Paso 3: Gestión Inteligente de Dashicons

#### Implementación Condicional
```php
function snn_conditional_dashicons() {
    // Solo cargar para usuarios logueados con permisos de editor+
    if (is_user_logged_in() && current_user_can('edit_posts')) {
        wp_enqueue_style('dashicons');
    }
}
add_action('wp_enqueue_scripts', 'snn_conditional_dashicons');
```

### Paso 4: Interfaz de Configuración Unificada

#### Estructura de la Página de Configuración
```php
// En security-optimization.php
function snn_render_security_optimization_page() {
    $sections = [
        'basic_security' => 'Configuraciones Básicas de Seguridad',
        'head_cleanup' => 'Limpieza Avanzada del Head',
        'loading_optimization' => 'Optimización de Carga',
        'protocol_settings' => 'Configuraciones de Protocolo',
        'resource_management' => 'Gestión de Recursos'
    ];
    
    // Renderizar cada sección con checkboxes organizados
}
```

#### Opciones de Configuración
```php
$security_options = [
    // Seguridad Básica
    'disable_xmlrpc' => 'Disable XML-RPC',
    'disable_json_api_guests' => 'Disable JSON API for Guests',
    'disable_file_editing' => 'Disable File Editing',
    'remove_rss_feeds' => 'Remove RSS Feeds',
    'hide_wp_version' => 'Hide WP Version',
    'disable_bundled_themes' => 'Disable Bundled Themes',
    'enable_math_captcha' => 'Enable Math Captcha',
    'disable_emojis' => 'Disable Emojis',
    'disable_gravatar' => 'Disable Gravatar',
    
    // Limpieza del Head
    'remove_wp_generator' => 'Remove WP Generator',
    'remove_wlw_manifest' => 'Remove WLW Manifest',
    'remove_rsd_link' => 'Remove RSD Link',
    'remove_shortlink' => 'Remove Shortlink',
    'remove_wp_json_links' => 'Remove WP JSON Links',
    'remove_oembed_links' => 'Remove OEmbed Links',
    'remove_dns_prefetch' => 'Remove DNS Prefetch',
    'remove_emoji_scripts' => 'Remove Emoji Scripts',
    'remove_wp_block_library' => 'Remove WP Block Library',
    
    // Optimización
    'optimize_css_loading' => 'Optimize CSS Loading',
    'preload_critical_fonts' => 'Preload Critical Fonts',
    'keep_essential_meta' => 'Keep Essential Meta',
    'keep_social_meta' => 'Keep Social Meta',
    'keep_seo_meta' => 'Keep SEO Meta',
    
    // Protocolo
    'force_https' => 'Force HTTPS',
    'fix_mixed_content' => 'Fix Mixed Content',
    'add_cors_headers' => 'Add CORS Headers',
    'protocol_detection' => 'Protocol Detection',
    'fix_font_urls' => 'Fix Font URLs'
];
```

### Paso 5: Integración con WindPress + Tailwind v4

#### Configuración de Capas CSS
```css
/* En main.css de WindPress */
@theme {
  --snn-primary: #0073aa;
  --snn-secondary: #29903b;
  --snn-font-family: 'Inter', sans-serif;
}

@layer base, components, utilities, custom;

@import "tailwindcss";
```

#### Optimizaciones de WordPress
```php
// Limitar CSS inline
add_filter('styles_inline_size_limit', function () { return 0; });

// Cargar estilos de bloques por demanda
add_filter('should_load_separate_core_block_assets', '__return_true');

// Remover global styles si no se usan
add_action('init', function () {
    remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
    remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
});
```

## Cronograma de Implementación

### Semana 1: Auditoría y Planificación
- [ ] Auditar archivos de seguridad existentes
- [ ] Documentar funcionalidades actuales
- [ ] Crear estructura de archivos nuevos

### Semana 2: Consolidación
- [ ] Crear `security-optimization.php`
- [ ] Migrar funcionalidades existentes
- [ ] Implementar nuevas funciones de limpieza del head

### Semana 3: Optimización
- [ ] Implementar optimizaciones de carga
- [ ] Configurar gestión de Dashicons
- [ ] Integrar con WindPress + Tailwind v4

### Semana 4: Testing y Refinamiento
- [ ] Testing exhaustivo
- [ ] Validar Core Web Vitals
- [ ] Ajustar configuraciones según resultados

## Métricas de Éxito

### Core Web Vitals
- LCP < 2.5s
- FID < 100ms  
- CLS < 0.1

### Seguridad
- Eliminación de información sensible del head
- Reducción de superficie de ataque
- Mejora en puntuación de seguridad

### Rendimiento
- Reducción del tiempo de carga
- Menor uso de recursos
- Mejor experiencia de usuario

## Consideraciones

### Compatibilidad
- Mantener compatibilidad con Bricks Builder
- Preservar funcionalidad de WindPress
- No romper elementos personalizados existentes

### Testing
- Probar en diferentes escenarios
- Validar en constructor Bricks
- Verificar compatibilidad con plugins comunes

### Documentación
- Documentar cada nueva funcionalidad
- Crear guías de uso
- Mantener actualizada la documentación
