<?php
/**
 * Assets Optimization
 * 
 * Advanced optimization for fonts, CSS, and JavaScript to improve Core Web Vitals
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SNN_Assets_Optimization {
    
    private $options;
    private $upload_dir;
    
    public function __construct() {
        // Solo inicializar en frontend, no en admin (excepto página de settings)
        if (is_admin()) {
            // Solo registrar settings en la página de configuración
            // Solo registrar settings en la página de configuración o al guardar opciones
            global $pagenow;
            if ((isset($pagenow) && $pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'snn-assets-optimization') || 
                (isset($pagenow) && $pagenow === 'options.php')) {
                add_action('admin_init', array($this, 'register_settings'));
            }
            
            // Initialize options for admin callbacks
            $this->options = get_option('snn_assets_options', array());
            
            // No ejecutar nada más en admin
            return;
        }
        
        // Solo inicializar en frontend
        $this->upload_dir = wp_upload_dir();
        $this->options = get_option('snn_assets_options', array());
        
        // Prevent Cloudflare PageSpeed from optimizing WordPress core CSS files
        // Must run early to set headers before assets are loaded
        add_action('send_headers', array($this, 'prevent_cloudflare_pagespeed_headers'), 1);
        
        // Apply optimizations (solo en frontend)
        add_action('wp_enqueue_scripts', array($this, 'apply_font_optimizations'), 1);
        add_action('wp_enqueue_scripts', array($this, 'apply_css_optimizations'), 1);
        add_action('wp_enqueue_scripts', array($this, 'apply_js_optimizations'), 1);
        
        // Critical CSS injection
        add_action('wp_head', array($this, 'inject_critical_css'), 1);
        
        // Font preloading
        add_action('wp_head', array($this, 'preload_critical_fonts'), 1);
        
        // Resource hints
        add_action('wp_head', array($this, 'add_resource_hints'), 1);
        
        // Google Tag Manager - DESHABILITADO: Ahora se gestiona desde Bricks Settings
        // Bricks Settings > Settings > Header scripts y Body (header) scripts
        // if ($this->options['optimize_third_party'] ?? true) {
        //     add_action('wp_head', array($this, 'optimize_gtm'), 0);
        // }
        
        // WP object safety check (muy temprano, antes de otros scripts)
        add_action('wp_head', array($this, 'add_wp_safety_check'), 0);
        
        // Defer non-critical JavaScript
        add_filter('script_loader_tag', array($this, 'defer_non_critical_js'), 10, 2);
        
        // Remove unused CSS
        // Priority 99999 to ensure it runs AFTER Jetpack and other plugins that enqueue wp-block-library
        add_action('wp_enqueue_scripts', array($this, 'remove_unused_css'), 99999);
        
        // Also remove on wp_head as a fallback (runs even later, just before output)
        add_action('wp_head', array($this, 'remove_unused_css_head'), 1);
        
        // Optimize third-party scripts
        add_action('wp_enqueue_scripts', array($this, 'optimize_third_party_scripts'), 999);
        
        // GTM noscript - DESHABILITADO: Ahora se gestiona desde Bricks Settings
        // Bricks Settings > Settings > Body (header) scripts
        // if ($this->options['optimize_third_party'] ?? true) {
        //     if (function_exists('wp_body_open')) {
        //         add_action('wp_body_open', array($this, 'add_gtm_noscript'), 1);
        //     } else {
        //         // Fallback for older WordPress versions: add at the very beginning of footer
        //         add_action('wp_footer', array($this, 'add_gtm_noscript'), 1);
        //     }
        // }
        
        // Enqueue Alpine.js Intersect plugin for Lazy Rendering
        // Priority 25 ensures it loads after Alpine Core (usually enqueued at priority 20)
        add_action('wp_enqueue_scripts', array($this, 'enqueue_alpine_intersect'), 25);
        
        // Prevenir auto-inicialización de Alpine hasta que el plugin Intersect esté listo
        add_action('wp_head', array($this, 'prevent_alpine_auto_init'), 1);
    }
    
    /**
     * Enqueue Alpine.js Intersect Plugin
     * Required for x-intersect to work in Bricks
     * 
     * IMPORTANT: This plugin MUST load AFTER Alpine Core
     * We use dependency on 'alpinejs' to ensure correct load order
     */
    public function enqueue_alpine_intersect() {
        // Don't load in builder to avoid conflicts
        if (function_exists('bricks_is_builder_main') && bricks_is_builder_main()) {
            return;
        }

        // Alpine Intersect Plugin (Must load AFTER Alpine Core)
        // Construir URL dinámicamente usando la URL del sitio
        $upload_dir = wp_upload_dir();
        $alpine_url = $upload_dir['baseurl'] . '/js/ialpine.min.js';
        
        wp_enqueue_script(
            'alpine-intersect',
            $alpine_url,
            array('alpinejs'), // Dependencia: Alpine Core debe cargarse primero
            '3.13.5',
            true // Load in footer (defer se agrega después)
        );
        
        // NO agregar defer al plugin Intersect - debe cargarse de forma síncrona
        // para que se registre antes de que Alpine se auto-inicialice
        // El plugin Intersect normalmente se auto-registra cuando se carga el script
        
        // Verificar que el plugin se registre correctamente
        add_action('wp_footer', array($this, 'verify_alpine_intersect'), 99);
    }
    
    /**
     * Prevent Alpine auto-initialization until Intersect plugin is ready
     */
    public function prevent_alpine_auto_init() {
        // Don't load in builder to avoid conflicts
        if (function_exists('bricks_is_builder_main') && bricks_is_builder_main()) {
            return;
        }
        ?>
        <script>
        // Prevenir auto-inicialización de Alpine hasta que el plugin Intersect esté listo
        (function() {
            // Guardar la función original de Alpine.start si existe
            if (typeof window.Alpine !== 'undefined' && window.Alpine.start) {
                window.Alpine._originalStart = window.Alpine.start;
                window.Alpine.start = function() {
                    // No inicializar aún, esperar al plugin
                    console.log('[Alpine Intersect] Auto-inicialización prevenida temporalmente');
                };
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Verify and register Alpine Intersect Plugin
     * This ensures the plugin is registered before Alpine auto-initializes
     */
    public function verify_alpine_intersect() {
        // Don't load in builder to avoid conflicts
        if (function_exists('bricks_is_builder_main') && bricks_is_builder_main()) {
            return;
        }
        ?>
        <script>
        (function() {
            // Función para registrar el plugin Intersect
            function registerIntersectPlugin() {
                // Verificar que Alpine esté disponible
                if (typeof Alpine === 'undefined') {
                    console.warn('[Alpine Intersect] Alpine.js no está disponible. Reintentando...');
                    setTimeout(registerIntersectPlugin, 50);
                    return;
                }
                
                // Verificar si el plugin Intersect está disponible
                // El plugin normalmente expone una función 'intersect' globalmente
                if (typeof intersect !== 'undefined') {
                    try {
                        // Registrar el plugin con Alpine
                        Alpine.plugin(intersect);
                        console.log('[Alpine Intersect] Plugin registrado correctamente');
                        
                        // Restaurar auto-inicialización de Alpine si la prevenimos
                        if (Alpine._originalStart) {
                            Alpine.start = Alpine._originalStart;
                            delete Alpine._originalStart;
                        }
                        
                        // Inicializar Alpine manualmente si no se ha inicializado
                        if (typeof Alpine.start === 'function') {
                            Alpine.start();
                            console.log('[Alpine Intersect] Alpine inicializado después de registrar plugin');
                        }
                        
                        // Verificar elementos con x-intersect
                        checkIntersectElements();
                    } catch (error) {
                        console.error('[Alpine Intersect] Error al registrar plugin:', error);
                    }
                } else {
                    // El plugin podría auto-registrarse, pero verificar después de un momento
                    console.log('[Alpine Intersect] Esperando auto-registro del plugin...');
                    setTimeout(function() {
                        // Si el plugin se auto-registró, restaurar Alpine
                        if (Alpine._originalStart) {
                            Alpine.start = Alpine._originalStart;
                            delete Alpine._originalStart;
                            Alpine.start();
                        }
                        // Verificar si Alpine tiene la directiva x-intersect disponible
                        checkIntersectElements();
                    }, 200);
                }
            }
            
            // Función para verificar y ayudar a inicializar elementos con x-intersect
            function checkIntersectElements() {
                // Buscar elementos con x-intersect (usando diferentes sintaxis posibles)
                const selectors = [
                    '[x-intersect]',
                    '[x-intersect\\.once]',
                    '[x-intersect\\.margin]',
                    '[x-intersect\\.once\\.margin]',
                    '[x-intersect*=""]' // Cualquier atributo que contenga x-intersect
                ];
                
                let intersectElements = [];
                selectors.forEach(selector => {
                    try {
                        const elements = document.querySelectorAll(selector);
                        intersectElements = intersectElements.concat(Array.from(elements));
                    } catch (e) {
                        // Selector inválido, ignorar
                    }
                });
                
                // Eliminar duplicados
                intersectElements = [...new Set(intersectElements)];
                
                if (intersectElements.length > 0) {
                    console.log('[Alpine Intersect] Encontrados', intersectElements.length, 'elementos con x-intersect');
                    
                    // Verificar que Alpine esté procesando estos elementos
                    // Si Alpine ya se inicializó, forzar re-evaluación
                    if (typeof Alpine !== 'undefined') {
                        // Forzar re-inicialización de Alpine en estos elementos
                        intersectElements.forEach(el => {
                            try {
                                // Si el elemento tiene x-data pero Alpine no lo ha procesado
                                if (el.hasAttribute('x-data') && !el.__x) {
                                    // Forzar inicialización manual si es necesario
                                    // Pero normalmente Alpine lo hace automáticamente
                                }
                            } catch (e) {
                                // Ignorar errores individuales
                            }
                        });
                    }
                } else {
                    console.log('[Alpine Intersect] No se encontraron elementos con x-intersect');
                }
            }
            
            // Inicializar cuando el DOM esté listo
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(registerIntersectPlugin, 100);
                });
            } else {
                setTimeout(registerIntersectPlugin, 100);
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'snn_assets_options',
            'snn_assets_options',
            array($this, 'sanitize_options')
        );
        
        add_settings_section(
            'snn_assets_fonts',
            __('Font Optimization', 'snn'),
            array($this, 'fonts_section_callback'),
            'snn-assets-optimization'
        );
        
        add_settings_field(
            'enable_font_preload',
            __('Enable Font Preloading', 'snn'),
            array($this, 'enable_font_preload_callback'),
            'snn-assets-optimization',
            'snn_assets_fonts'
        );
        
        add_settings_field(
            'critical_fonts',
            __('Critical Fonts', 'snn'),
            array($this, 'critical_fonts_callback'),
            'snn-assets-optimization',
            'snn_assets_fonts'
        );
        
        add_settings_field(
            'font_display_swap',
            __('Font Display Swap', 'snn'),
            array($this, 'font_display_swap_callback'),
            'snn-assets-optimization',
            'snn_assets_fonts'
        );
        
        add_settings_section(
            'snn_assets_css',
            __('CSS Optimization', 'snn'),
            array($this, 'css_section_callback'),
            'snn-assets-optimization'
        );
        
        add_settings_field(
            'enable_critical_css',
            __('Enable Critical CSS', 'snn'),
            array($this, 'enable_critical_css_callback'),
            'snn-assets-optimization',
            'snn_assets_css'
        );
        
        add_settings_field(
            'critical_css_content',
            __('Critical CSS Content', 'snn'),
            array($this, 'critical_css_content_callback'),
            'snn-assets-optimization',
            'snn_assets_css'
        );
        
        add_settings_field(
            'remove_unused_css',
            __('Remove Unused CSS', 'snn'),
            array($this, 'remove_unused_css_callback'),
            'snn-assets-optimization',
            'snn_assets_css'
        );
        
        add_settings_field(
            'css_minification',
            __('CSS Minification', 'snn'),
            array($this, 'css_minification_callback'),
            'snn-assets-optimization',
            'snn_assets_css'
        );
        
        add_settings_section(
            'snn_assets_js',
            __('JavaScript Optimization', 'snn'),
            array($this, 'js_section_callback'),
            'snn-assets-optimization'
        );
        
        add_settings_field(
            'defer_non_critical_js',
            __('Defer Non-Critical JS', 'snn'),
            array($this, 'defer_non_critical_js_callback'),
            'snn-assets-optimization',
            'snn_assets_js'
        );
        
        add_settings_field(
            'js_minification',
            __('JavaScript Minification', 'snn'),
            array($this, 'js_minification_callback'),
            'snn-assets-optimization',
            'snn_assets_js'
        );
        
        add_settings_field(
            'remove_console_logs',
            __('Remove Console Logs', 'snn'),
            array($this, 'remove_console_logs_callback'),
            'snn-assets-optimization',
            'snn_assets_js'
        );
        
        add_settings_field(
            'optimize_third_party',
            __('Optimize Third-Party Scripts', 'snn'),
            array($this, 'optimize_third_party_callback'),
            'snn-assets-optimization',
            'snn_assets_js'
        );
        
        // Google Tag Manager ID - DESHABILITADO: Ahora se gestiona desde Bricks Settings
        // add_settings_field(
        //     'gtm_id',
        //     __('Google Tag Manager ID', 'snn'),
        //     array($this, 'gtm_id_callback'),
        //     'snn-assets-optimization',
        //     'snn_assets_js'
        // );
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        // Font options
        $sanitized['enable_font_preload'] = isset($input['enable_font_preload']) ? (bool) $input['enable_font_preload'] : true;
        $sanitized['critical_fonts'] = isset($input['critical_fonts']) ? sanitize_textarea_field($input['critical_fonts']) : '';
        $sanitized['font_display_swap'] = isset($input['font_display_swap']) ? (bool) $input['font_display_swap'] : true;
        
        // CSS options
        $sanitized['enable_critical_css'] = isset($input['enable_critical_css']) ? (bool) $input['enable_critical_css'] : true;
        $sanitized['critical_css_content'] = isset($input['critical_css_content']) ? wp_kses_post($input['critical_css_content']) : '';
        $sanitized['remove_unused_css'] = isset($input['remove_unused_css']) ? (bool) $input['remove_unused_css'] : true;
        $sanitized['css_minification'] = isset($input['css_minification']) ? (bool) $input['css_minification'] : true;
        
        // JavaScript options
        $sanitized['defer_non_critical_js'] = isset($input['defer_non_critical_js']) ? (bool) $input['defer_non_critical_js'] : true;
        $sanitized['js_minification'] = isset($input['js_minification']) ? (bool) $input['js_minification'] : true;
        $sanitized['remove_console_logs'] = isset($input['remove_console_logs']) ? (bool) $input['remove_console_logs'] : true;
        $sanitized['optimize_third_party'] = isset($input['optimize_third_party']) ? (bool) $input['optimize_third_party'] : true;
        // GTM ID - DESHABILITADO: Ahora se gestiona desde Bricks Settings
        // $sanitized['gtm_id'] = isset($input['gtm_id']) ? sanitize_text_field($input['gtm_id']) : '';
        
        return $sanitized;
    }
    
    /**
     * Apply font optimizations
     */
    public function apply_font_optimizations() {
        if (!($this->options['enable_font_preload'] ?? true)) {
            return;
        }
        
        // Add font-display: swap to all font-face declarations
        if ($this->options['font_display_swap'] ?? true) {
            add_action('wp_head', array($this, 'add_font_display_swap'), 1);
        }
    }
    
    /**
     * Apply CSS optimizations
     */
    public function apply_css_optimizations() {
        // Remove unused CSS
        if ($this->options['remove_unused_css'] ?? true) {
            $this->remove_unused_css();
        }
        
        // Prevent Cloudflare PageSpeed from optimizing WordPress core CSS files
        // Cloudflare generates invalid URLs like: A.style.min.css,qver=6.8.3.pagespeed.cf.xxx.css
        add_filter('style_loader_src', array($this, 'prevent_cloudflare_pagespeed_on_core_css'), 5, 2);
        
        // Minify CSS (but exclude core files)
        if ($this->options['css_minification'] ?? true) {
            add_filter('style_loader_src', array($this, 'add_css_minification'), 10, 2);
        }
        
        // Load CSS asynchronously if Critical CSS is enabled
        // MODIFICACIÓN: Ahora siempre cargamos CSS de forma asíncrona (preload) para mejorar rendimiento
        // incluso si el CSS crítico está vacío, ya que esto ayuda a que el LCP ocurra antes.
        // Solo lo desactivamos si explicitamente se deshabilita critical CSS en opciones.
        if ($this->options['enable_critical_css'] ?? true) {
            add_filter('style_loader_tag', array($this, 'load_css_asynchronously'), 10, 4);
        }
    }
    
    /**
     * Apply JavaScript optimizations
     */
    public function apply_js_optimizations() {
        // Defer non-critical JavaScript
        if ($this->options['defer_non_critical_js'] ?? true) {
            add_filter('script_loader_tag', array($this, 'defer_non_critical_js'), 10, 2);
        }
        
        // Minify JavaScript
        if ($this->options['js_minification'] ?? true) {
            add_filter('script_loader_src', array($this, 'add_js_minification'), 10, 2);
        }
        
        // Remove console logs
        if ($this->options['remove_console_logs'] ?? true) {
            add_action('wp_head', array($this, 'remove_console_logs'), 999);
        }
    }
    
    /**
     * Inject critical CSS
     */
    public function inject_critical_css() {
        if (!($this->options['enable_critical_css'] ?? true)) {
            return;
        }
        
        $critical_css = $this->options['critical_css_content'] ?? '';
        if (empty($critical_css)) {
            return;
        }
        
        echo '<style id="snn-critical-css">' . $critical_css . '</style>';
    }
    
    /**
     * Preload critical fonts
     */
    public function preload_critical_fonts() {
        if (!($this->options['enable_font_preload'] ?? true)) {
            return;
        }
        
        $critical_fonts = $this->options['critical_fonts'] ?? '';
        if (empty($critical_fonts)) {
            return;
        }
        
        // Array para rastrear fuentes ya preloadadas y evitar duplicados
        static $preloaded_fonts = array();
        
        $fonts = explode("\n", $critical_fonts);
        foreach ($fonts as $font) {
            $font = trim($font);
            if (!empty($font)) {
                // Evitar preloads duplicados del mismo recurso
                if (isset($preloaded_fonts[$font])) {
                    continue;
                }
                $preloaded_fonts[$font] = true;
                
                // Usar crossorigin="anonymous" en lugar de solo crossorigin
                echo '<link rel="preload" href="' . esc_url($font) . '" as="font" type="font/woff2" crossorigin="anonymous">' . "\n";
            }
        }
    }
    
    /**
     * Add resource hints
     * Optimized for Core Web Vitals:
     * - Preconnect for critical resources (fonts) - does DNS + TCP + TLS
     * - DNS prefetch only for non-critical resources - lighter weight
     */
    public function add_resource_hints() {
        // DNS prefetch for non-critical external domains only
        // Only use dns-prefetch for resources that don't need immediate connection
        // This reduces HTML size while still providing DNS resolution benefits
        $non_critical_domains = array(
            'cdnjs.cloudflare.com',
            'unpkg.com'
        );
        
        foreach ($non_critical_domains as $domain) {
            echo '<link rel="dns-prefetch" href="//' . esc_attr($domain) . '">' . "\n";
        }
    }
    
    /**
     * Add font-display: swap to font-face declarations
     */
    public function add_font_display_swap() {
        echo '<style>
        @font-face {
            font-display: swap;
        }
        </style>';
    }
    
    /**
     * Defer non-critical JavaScript
     */
    public function defer_non_critical_js($tag, $handle) {
        // No aplicar en admin
        if (is_admin()) {
            return $tag;
        }
        // Critical scripts that should not be deferred
        $critical_scripts = array(
            'jquery',
            'jquery-core',
            'jquery-migrate',
            'bricks-frontend',
            'snn-webp-optimization',
            'snn-assets-optimization'
        );
        
        if (in_array($handle, $critical_scripts)) {
            return $tag;
        }
        
        // Defer all other scripts
        return str_replace('<script ', '<script defer ', $tag);
    }
    
    /**
     * Remove unused CSS
     * CRITICAL: This must run AFTER Jetpack and other plugins that enqueue wp-block-library
     */
    public function remove_unused_css() {
        // Remove unused WordPress CSS
        // Jetpack is known to enqueue wp-block-library, so we need to dequeue it here
        wp_dequeue_style('wp-block-library');
        wp_deregister_style('wp-block-library');
        
        wp_dequeue_style('wp-block-library-theme');
        wp_deregister_style('wp-block-library-theme');
        
        wp_dequeue_style('global-styles');
        wp_dequeue_style('classic-theme-styles');
        
        // Remove unused Bricks CSS if not in builder
        if (!bricks_is_builder_main()) {
            wp_dequeue_style('bricks-admin');
        }
    }
    
    /**
     * Remove unused CSS from head (fallback - runs just before HTML output)
     * This ensures we remove it even if plugins enqueue it very late
     */
    public function remove_unused_css_head() {
        // Final attempt to remove wp-block-library before it's output
        wp_dequeue_style('wp-block-library');
        wp_deregister_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_deregister_style('wp-block-library-theme');
    }
    
    /**
     * Optimize third-party scripts
     */
    public function optimize_third_party_scripts() {
        if (!($this->options['optimize_third_party'] ?? true)) {
            return;
        }
        
        // Google Tag Manager - DESHABILITADO: Ahora se gestiona desde Bricks Settings
        // add_action('wp_head', array($this, 'optimize_gtm'), 1);
        
        // Optimize Facebook Pixel
        add_action('wp_head', array($this, 'optimize_facebook_pixel'), 1);
    }
    
    /**
     * Optimize Google Tag Manager (Core Web Vitals Optimized)
     * 
     * DESHABILITADO: Ahora se gestiona desde Bricks Settings
     * Bricks Settings > Settings > Header scripts
     * 
     * Strategy:
     * 1. Initialize dataLayer immediately (non-blocking, prevents data loss)
     * 2. Load GTM script with defer attribute (non-blocking, doesn't affect render)
     * 3. This ensures GTM loads correctly while maintaining Core Web Vitals
     */
    /* DESHABILITADO: GTM ahora se gestiona desde Bricks Settings
    public function optimize_gtm() {
        // Only load if not already loaded
        if (wp_script_is('google-tag-manager', 'enqueued')) {
            return;
        }
        
        $gtm_id = $this->options['gtm_id'] ?? '';
        if (empty($gtm_id)) {
            return;
        }
        
        // Hybrid Loading Strategy:
        // 1. Initialize dataLayer immediately (prevents data loss)
        // 2. Load script on interaction (scroll, mousemove, touch) OR
        // 3. Load script automatically after 4 seconds (safety timeout)
        ?>
        <script>
        // 1. Init dataLayer immediately
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        
        // 2. Hybrid Loader
        (function(w,d,s,l,i){
            var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
            j.async=true;
            j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
            
            var loaded = false;
            
            function loadGTM() {
                if (loaded) return;
                loaded = true;
                f.parentNode.insertBefore(j,f);
                // Remove listeners
                window.removeEventListener('scroll', loadGTM);
                window.removeEventListener('mousemove', loadGTM);
                window.removeEventListener('touchstart', loadGTM);
            }
            
            // Load on interaction
            window.addEventListener('scroll', loadGTM, {passive: true});
            window.addEventListener('mousemove', loadGTM, {passive: true});
            window.addEventListener('touchstart', loadGTM, {passive: true});
            
            // Safety Timeout (4 seconds)
            setTimeout(loadGTM, 4000);
            
        })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');
        </script>
        <!-- End Google Tag Manager -->
        <!-- 
        NOTA: Para reducir completamente las advertencias de cookies de terceros:
        
        En Google Tag Manager, configura tus etiquetas de GA4:
        1. Ve a tu etiqueta "Google Analytics: GA4 Configuration"
        2. En "Más configuraciones" > "Campos para configurar"
        3. Agrega estos campos (si no están ya configurados):
           - cookie_flags: SameSite=None;Secure
           - cookie_update: true
           - cookie_expires: 63072000
        
        Esto ayudará a que GA4 use cookies de primera parte cuando sea posible.
        -->
        <?php
    }
    */
    
    /**
     * Add GTM noscript code after body tag
     * 
     * DESHABILITADO: Ahora se gestiona desde Bricks Settings
     * Bricks Settings > Settings > Body (header) scripts
     * 
     * Required for GTM to work when JavaScript is disabled
     */
    /* DESHABILITADO: GTM ahora se gestiona desde Bricks Settings
    public function add_gtm_noscript() {
        // Only load if optimize_third_party is enabled
        if (!($this->options['optimize_third_party'] ?? true)) {
            return;
        }
        
        $gtm_id = $this->options['gtm_id'] ?? '';
        if (empty($gtm_id)) {
            return;
        }
        
        // Clean GTM ID (remove any spaces or invalid characters)
        $gtm_id = preg_replace('/[^A-Z0-9-]/', '', strtoupper($gtm_id));
        
        ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($gtm_id); ?>"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <?php
    }
    */
    
    /**
     * Optimize Facebook Pixel
     */
    public function optimize_facebook_pixel() {
        // Only load FB Pixel if not already loaded
        if (wp_script_is('facebook-pixel', 'enqueued')) {
            return;
        }
        
        // Add optimized FB Pixel loading
        echo '<script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,"script",
        "https://connect.facebook.net/en_US/fbevents.js");
        </script>';
    }
    
    /**
     * Remove console logs
     */
    public function remove_console_logs() {
        echo '<script>
        if (typeof console !== "undefined") {
            console.log = function() {};
            console.warn = function() {};
            console.error = function() {};
        }
        </script>';
    }
    
    /**
     * Add wp object safety check to prevent undefined errors
     */
    public function add_wp_safety_check() {
        // Solo en frontend, no en admin
        if (is_admin()) {
            return;
        }
        
        ?>
        <script>
        // Protección global para prevenir errores de wp no definido
        (function() {
            if (typeof wp === "undefined") {
                window.wp = {
                    media: function() {
                        console.warn("wp.media no está disponible en el frontend");
                        return {
                            on: function() { return this; },
                            open: function() { return this; }
                        };
                    },
                    data: null,
                    codeEditor: null,
                    i18n: {
                        setLocaleData: function() {
                            // No-op: prevenir errores cuando plugins intentan usar i18n en frontend
                            return;
                        },
                        __: function(text) {
                            return text;
                        },
                        _x: function(text, context) {
                            return text;
                        },
                        _n: function(single, plural, number) {
                            return number === 1 ? single : plural;
                        },
                        sprintf: function(format) {
                            var args = Array.prototype.slice.call(arguments, 1);
                            var percentChar = String.fromCharCode(37);
                            return format.replace(/%[sdj%]/g, function(match) {
                                if (match === percentChar + percentChar) return percentChar;
                                var arg = args.shift();
                                if (arg === undefined || arg === null) return '';
                                if (match === '%j') return JSON.stringify(arg);
                                return String(arg);
                            });
                        }
                    }
                };
            } else {
                // Si wp existe pero i18n no, agregarlo
                if (!wp.i18n) {
                    wp.i18n = {
                        setLocaleData: function() {
                            return;
                        },
                        __: function(text) {
                            return text;
                        },
                        _x: function(text, context) {
                            return text;
                        },
                        _n: function(single, plural, number) {
                            return number === 1 ? single : plural;
                        },
                        sprintf: function(format) {
                            var args = Array.prototype.slice.call(arguments, 1);
                            var percentChar = String.fromCharCode(37);
                            return format.replace(/%[sdj%]/g, function(match) {
                                if (match === percentChar + percentChar) return percentChar;
                                var arg = args.shift();
                                if (arg === undefined || arg === null) return '';
                                if (match === '%j') return JSON.stringify(arg);
                                return String(arg);
                            });
                        }
                    };
                }
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Set headers to prevent Cloudflare PageSpeed from optimizing WordPress core CSS files
     * This prevents invalid URLs like: A.style.min.css,qver=6.8.3.pagespeed.cf.xxx.css
     */
    public function prevent_cloudflare_pagespeed_headers() {
        // Add header to exclude WordPress core CSS files from Cloudflare PageSpeed optimization
        // This is a general header that tells Cloudflare not    public function prevent_cloudflare_pagespeed_headers() {
        if (!headers_sent()) {
            // Note: Cloudflare may not respect this header, but it's worth trying
            // The best solution is to configure Cloudflare settings directly
            // header('X-Robots-Tag: noindex, nofollow', false); // REMOVED to allow indexing
            
            // Alternative: Add a meta tag in HTML (done via wp_head)
            // Or configure Cloudflare via Page Rules to exclude /wp-includes/css/
        }
    }
    
    /**
     * Prevent Cloudflare PageSpeed from optimizing WordPress core CSS files via URL modification
     * This prevents invalid URLs like: A.style.min.css,qver=6.8.3.pagespeed.cf.xxx.css
     */
    public function prevent_cloudflare_pagespeed_on_core_css($src, $handle) {
        if (empty($src)) {
            return $src;
        }
        
        // Check if this is a WordPress core CSS file
        $is_core_css = (
            strpos($src, '/wp-includes/css/') !== false ||
            strpos($src, '/wp-admin/css/') !== false ||
            strpos($src, '/wp-content/themes/twenty') !== false // Default themes
        );
        
        if ($is_core_css) {
            // Remove any existing query parameters that might trigger Cloudflare optimization
            // and add a parameter to prevent optimization
            $url_parts = parse_url($src);
            $src = $url_parts['scheme'] . '://' . $url_parts['host'] . 
                   (isset($url_parts['port']) ? ':' . $url_parts['port'] : '') .
                   $url_parts['path'];
            
            // Keep version parameter if it exists (important for cache busting)
            if (isset($url_parts['query'])) {
                parse_str($url_parts['query'], $query_params);
                // Keep 'ver' parameter
                if (isset($query_params['ver'])) {
                    $src = add_query_arg('ver', $query_params['ver'], $src);
                }
            }
            
            // Add parameter to discourage Cloudflare optimization
            // Note: This may not work as Cloudflare controls this at edge level
            $src = add_query_arg('cf_no_optimize', '1', $src);
        }
        
        return $src;
    }
    
    /**
     * Load CSS asynchronously
     * Uses rel="preload" pattern for high-priority non-blocking loading
     */
    public function load_css_asynchronously($html, $handle, $href, $media) {
        // Don't defer in builder or if it's not a stylesheet
        if (is_admin() || (function_exists('bricks_is_builder_main') && bricks_is_builder_main())) {
            return $html;
        }

        // Only defer 'all' or 'screen' media
        if ($media !== 'all' && $media !== 'screen' && !empty($media)) {
            return $html;
        }
        
        // Skip specific handles that might be needed immediately if not in critical CSS
        // wp-block-library is often safe to defer if we have critical CSS
        $excluded_handles = array('admin-bar', 'dashicons');
        if (in_array($handle, $excluded_handles)) {
            return $html;
        }

        // Strategy: Use rel="preload" (High Priority, Non-blocking)
        // Instead of media="print" (Low Priority), we use preload to fetch it ASAP
        // but without blocking the render.
        
        $async_html = $html;
        
        // Replace rel='stylesheet' with rel='preload' as='style' and add onload handler
        // We use regex to be robust against attribute order
        $async_html = preg_replace(
            '/\brel=["\']stylesheet["\']/',
            'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"',
            $async_html
        );
        
        // If regex failed (didn't match), fallback to simple replace (less robust but works for standard WP output)
        if ($async_html === $html) {
             $async_html = str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $html);
        }
        
        // Add noscript fallback for users with JS disabled
        $async_html .= '<noscript><link rel="stylesheet" href="' . esc_url($href) . '"></noscript>';
        
        return $async_html;
    }

    /**
     * Add CSS minification (excludes core WordPress files)
     */
    public function add_css_minification($src, $handle) {
        if (empty($src)) {
            return $src;
        }
        
        // Skip minification for WordPress core CSS files
        // These files should not be processed by Cloudflare PageSpeed
        $is_core_css = (
            strpos($src, '/wp-includes/css/') !== false ||
            strpos($src, '/wp-admin/css/') !== false ||
            strpos($src, '/wp-content/themes/twenty') !== false
        );
        
        if ($is_core_css) {
            // Don't add minification parameter for core files
            return $src;
        }
        
        // Add minification parameter for other CSS files
        return add_query_arg('minify', 'true', $src);
    }
    
    /**
     * Add JavaScript minification
     */
    public function add_js_minification($src, $handle) {
        // Add minification parameter
        return add_query_arg('minify', 'true', $src);
    }
    
    // Settings callbacks
    public function fonts_section_callback() {
        echo '<p>' . __('Configure font optimization settings for better Core Web Vitals.', 'snn') . '</p>';
    }
    
    public function css_section_callback() {
        echo '<p>' . __('Configure CSS optimization settings for better performance.', 'snn') . '</p>';
    }
    
    public function js_section_callback() {
        echo '<p>' . __('Configure JavaScript optimization settings for better performance.', 'snn') . '</p>';
    }
    
    public function enable_font_preload_callback() {
        $value = $this->options['enable_font_preload'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[enable_font_preload]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Preload critical fonts for faster rendering.', 'snn') . '</p>';
    }
    
    public function critical_fonts_callback() {
        $value = $this->options['critical_fonts'] ?? '';
        echo '<textarea name="snn_assets_options[critical_fonts]" rows="5" cols="50">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Enter one font URL per line. These fonts will be preloaded.', 'snn') . '</p>';
    }
    
    public function font_display_swap_callback() {
        $value = $this->options['font_display_swap'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[font_display_swap]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Use font-display: swap to prevent invisible text during font load.', 'snn') . '</p>';
    }
    
    public function enable_critical_css_callback() {
        $value = $this->options['enable_critical_css'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[enable_critical_css]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Inject critical CSS inline for faster rendering.', 'snn') . '</p>';
    }
    
    public function critical_css_content_callback() {
        $value = $this->options['critical_css_content'] ?? '';
        echo '<textarea name="snn_assets_options[critical_css_content]" rows="10" cols="50">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Enter critical CSS that should be loaded inline.', 'snn') . '</p>';
    }
    
    public function remove_unused_css_callback() {
        $value = $this->options['remove_unused_css'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[remove_unused_css]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Remove unused CSS from WordPress and plugins.', 'snn') . '</p>';
    }
    
    public function css_minification_callback() {
        $value = $this->options['css_minification'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[css_minification]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Minify CSS files for smaller file sizes.', 'snn') . '</p>';
    }
    
    public function defer_non_critical_js_callback() {
        $value = $this->options['defer_non_critical_js'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[defer_non_critical_js]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Defer non-critical JavaScript for faster page load.', 'snn') . '</p>';
    }
    
    public function js_minification_callback() {
        $value = $this->options['js_minification'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[js_minification]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Minify JavaScript files for smaller file sizes.', 'snn') . '</p>';
    }
    
    public function remove_console_logs_callback() {
        $value = $this->options['remove_console_logs'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[remove_console_logs]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Remove console.log statements from production.', 'snn') . '</p>';
    }
    
    public function optimize_third_party_callback() {
        $value = $this->options['optimize_third_party'] ?? true;
        echo '<input type="checkbox" name="snn_assets_options[optimize_third_party]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Optimize third-party scripts like Google Analytics and Facebook Pixel.', 'snn') . '</p>';
    }

    // GTM ID Callback - DESHABILITADO: Ahora se gestiona desde Bricks Settings
    // public function gtm_id_callback() {
    //     $value = $this->options['gtm_id'] ?? '';
    //     echo '<input type="text" name="snn_assets_options[gtm_id]" value="' . esc_attr($value) . '" class="regular-text">';
    //     echo '<p class="description">' . __('Enter your Google Tag Manager ID (e.g., GTM-XXXXXX).', 'snn') . '</p>';
    // }
}

// Initialize the class (siempre, pero el constructor maneja cuándo ejecutar código)
new SNN_Assets_Optimization();
