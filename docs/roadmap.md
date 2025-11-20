# Roadmap del Proyecto SNN-BRX-WIL

## Objetivo Principal
Optimizar el tema hijo de Bricks Builder para mejorar Core Web Vitals, seguridad y experiencia de desarrollo, manteniendo compatibilidad con WindPress y Tailwind v4.

## Fase 1: Seguridad y Optimización del Head (Prioridad Alta)

### 1.1 Security Settings Unificado
**Objetivo**: Centralizar todas las configuraciones de seguridad en una interfaz unificada.

#### Configuraciones de Seguridad Básica
- [x] Disable XML-RPC
- [x] Disable JSON API for Guests  
- [x] Disable File Editing
- [x] Remove RSS Feeds
- [x] Hide WP Version
- [x] Disable Bundled Themes
- [x] Enable Math Captcha
- [x] Disable Emojis
- [x] Disable Gravatar

#### Limpieza Avanzada del Head
- [x] Remove WP Generator ✅ (implementado en head-optimization.php)
- [x] Remove WLW Manifest ✅ (implementado en head-optimization.php)
- [x] Remove RSD Link ✅ (implementado en head-optimization.php)
- [x] Remove Shortlink ✅ (implementado en head-optimization.php)
- [x] Remove WP JSON Links ✅ (implementado en head-optimization.php)
- [x] Remove OEmbed Links ✅ (implementado en head-optimization.php)
- [x] Remove DNS Prefetch ✅ (implementado en head-optimization.php)
- [x] Remove Emoji Scripts ✅ (configurable en Security & Optimization - Disable Emojis)
- [x] Remove WP Block Library ✅ (configurable en Security & Optimization)

#### Optimización de Carga
- [x] Optimize CSS Loading ✅ (implementado con preload para Bricks, theme-specific y WindPress)
- [x] Preload Critical Fonts ✅ (movido a Assets Optimization)
- [x] Keep Social Meta ✅ (implementado - genera Open Graph y Twitter Cards para compartir en redes sociales) ⚠️ Pendiente pruebas en producción
- [x] Keep SEO Meta ✅ (implementado - genera canonical y robots meta tags) ⚠️ Pendiente pruebas en producción

#### Configuraciones de Protocolo
- [x] Force HTTPS ✅ (implementado, requiere HTTPS configurado)
- [x] Fix Mixed Content automáticamente ✅ (implementado con Content-Security-Policy)
- [x] Add CORS Headers ✅ (implementado con excepción para REST API)
- [x] Protocol Detection en JavaScript ✅ (implementado)
- [x] Fix Font URLs ✅ (implementado con font-display: swap)

### 1.2 Gestión Inteligente de Dashicons
**Objetivo**: Cargar Dashicons solo cuando sea necesario (usuarios logueados con permisos de editor+).
- [x] ✅ Implementado en head-optimization.php

### 1.3 Integración con WindPress + Tailwind v4
**Objetivo**: Implementar arquitectura de capas CSS según [Flowtitude](https://flowtitude.com/layer-css-wordpress-tailwind).

#### Estructura de Capas Propuesta
```css
@layer base, components, utilities, custom;
```

- **base**: reset/preflight, tipografía global, normalizaciones
- **components**: patrones reutilizables (.btn, .card, .input)
- **utilities**: helpers pequeños cuando no exista utilidad Tailwind
- **custom**: último recurso para convivir con CSS de terceros

#### Optimizaciones Específicas
- [ ] Implementar carga condicional de estilos de bloques
- [ ] Limitar CSS inline de WordPress
- [ ] Encapsular CSS de terceros en capa controlada
- [ ] Configurar tokens con `@theme`

## Fase 2: Core Web Vitals (Prioridad Alta)

### 2.1 Optimización de Imágenes
- [x] Conversión automática a WebP ✅ (implementado en webp-image-optimizer.php)
- [x] Optimización automática de imágenes (redimensionamiento) ✅ (implementado en image-auto-optimizer.php)
- [x] Preload de imágenes críticas ✅ (implementado)
- [x] Lazy loading inteligente ✅ (implementado)
- [x] Width/Height explícitos ✅ (implementado)
- [x] Optimización de tamaños responsive ✅ (implementado)
- [x] LCP mejorado (fetchpriority dinámico) ✅ (implementado - solo la primera imagen crítica tiene fetchpriority="high", detecta featured image, primera imagen del contenido, y primera imagen de galerías)
- [ ] Preload más específico (imagesrcset, imagesizes) ⚠️

### 2.2 Optimización de CSS/JS
- [ ] Minificación y concatenación ⚠️ (parcial en assets-optimization.php)
- [ ] Carga diferida de scripts no críticos ⚠️ (parcial)
- [ ] Eliminación de CSS no utilizado ⚠️
- [ ] Critical CSS inline ⚠️ (prioridad alta para Speed Index)
- [ ] Carga condicional JS completa (GSAP, Leaflet, Lottie, etc.) ⚠️ (289 KiB, prioridad alta)
- [ ] Preconnect para dominios externos ⚠️
- [ ] Consolidar CSS cuando sea posible ⚠️

### 2.3 Optimización de Fuentes
- [ ] Preload de fuentes críticas ⚠️ (parcial)
- [ ] Font-display: swap ⚠️
- [ ] Subset de fuentes cuando sea posible ⚠️

### 2.4 Layout Shifts (CLS)
- [x] Width/height en imágenes ✅
- [ ] Reservar espacio para banners/ads ⚠️
- [ ] Evitar contenido dinámico sin reservar espacio ⚠️
- [ ] Revisar fuentes (font-display: swap) ⚠️

### 2.5 TTFB Optimization
- [ ] Optimizar consultas pesadas en `init` y `template_redirect` ⚠️
- [ ] Reducir autoload de `wp_options` ⚠️
- [ ] Revisar hooks innecesarios ⚠️
- **Nota**: Principalmente responsabilidad del servidor/Cloudflare, pero el theme puede ayudar

### 2.6 DOM Optimization
- [ ] Simplificar plantillas HTML ⚠️
- [ ] Reducir wrappers innecesarios ⚠️
- [ ] Límite de posts en home (configurable) ⚠️
- **Nota**: Limitado por Bricks Builder (genera su propio HTML)

## Fase 3: Funcionalidades Avanzadas (Prioridad Media)

### 3.1 Generador de Sitemap XML
- [x] Creación automática de sitemap.xml ✅ (implementado en xml-sitemaps-generator.php)
- [ ] Integración con Google Search Console ⚠️
- [x] Configuración de prioridades y frecuencias ✅
- [ ] Sitemap para imágenes y videos ⚠️

### 3.2 Sistema de Optimización de Imágenes
- [x] Conversión automática a WebP ✅ (ya implementado en 2.1)
- [x] Generación de múltiples tamaños ✅ (ya implementado en 2.1)
- [x] Compresión inteligente ✅ (ya implementado en 2.1)
- [x] Integración con CDN ✅ (detecta Cloudflare automáticamente)

## Consideraciones Técnicas

### Compatibilidad
- Mantener compatibilidad con Bricks Builder
- Preservar funcionalidad de WindPress
- No romper elementos personalizados existentes

### Patrones a Mantener
- Prefijo `snn_` para funciones
- Estructura modular en `includes/`
- Sistema de configuración con Settings API
- Internacionalización con dominio `snn`

### Testing
- Pruebas manuales en diferentes escenarios
- Validación de Core Web Vitals
- Testing de compatibilidad con plugins comunes

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

## Fase 4: Accesibilidad y SEO (Prioridad Media-Baja)

### 4.1 Accesibilidad
- [ ] Revisar variables de color en CSS (contraste mínimo 4.5:1) ⚠️
- [ ] Agregar `aria-label` a enlaces con solo iconos ⚠️
- [ ] Revisar componentes de navegación ⚠️

### 4.2 SEO
- [ ] Agregar meta description en templates principales ⚠️
- [ ] Usar excerpt como fallback ⚠️

## Próximos Pasos

1. **Testing**: Validar mejoras en Core Web Vitals (ver [`tests/`](../tests/))
2. **Fase 2 - Prioridad Alta**: 
   - LCP mejorado (fetchpriority dinámico)
   - Carga condicional JS completa (289 KiB)
3. **Fase 2 - Prioridad Media**: 
   - Critical CSS inline (Speed Index)
   - Layout Shifts (reservar espacio)
4. **Iteración**: Ajustar basado en resultados de testing
5. **Fase 4**: Accesibilidad y SEO

## Referencias

- **Tests disponibles**: [`tests/INDEX.md`](../tests/INDEX.md)

---

*Este roadmap es un documento vivo que se actualizará conforme avance el proyecto.*
