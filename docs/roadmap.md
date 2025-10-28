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
- [ ] Remove WP Generator
- [ ] Remove WLW Manifest
- [ ] Remove RSD Link
- [ ] Remove Shortlink
- [ ] Remove WP JSON Links
- [ ] Remove OEmbed Links
- [ ] Remove DNS Prefetch
- [ ] Remove Emoji Scripts
- [ ] Remove WP Block Library

#### Optimización de Carga
- [ ] Optimize CSS Loading con font preloading
- [ ] Preload Critical Fonts
- [ ] Keep Essential Meta (charset, viewport)
- [ ] Keep Social Meta (Open Graph, Twitter Card)
- [ ] Keep SEO Meta (canonical, robots)

#### Configuraciones de Protocolo
- [ ] Force HTTPS (con precaución)
- [ ] Fix Mixed Content automáticamente
- [ ] Add CORS Headers para recursos same-domain
- [ ] Protocol Detection en JavaScript
- [ ] Fix Font URLs para prevenir errores CORS

### 1.2 Gestión Inteligente de Dashicons
**Objetivo**: Cargar Dashicons solo cuando sea necesario (usuarios logueados con permisos de editor+).

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
- [ ] Conversión automática a WebP
- [ ] Preload de imágenes críticas
- [ ] Lazy loading inteligente
- [ ] Optimización de tamaños responsive

### 2.2 Optimización de CSS/JS
- [ ] Minificación y concatenación
- [ ] Carga diferida de scripts no críticos
- [ ] Eliminación de CSS no utilizado
- [ ] Critical CSS inline

### 2.3 Optimización de Fuentes
- [ ] Preload de fuentes críticas
- [ ] Font-display: swap
- [ ] Subset de fuentes cuando sea posible

## Fase 3: Funcionalidades Avanzadas (Prioridad Media)

### 3.1 Generador de Sitemap XML
- [ ] Creación automática de sitemap.xml
- [ ] Integración con Google Search Console
- [ ] Configuración de prioridades y frecuencias
- [ ] Sitemap para imágenes y videos

### 3.2 Sistema de Optimización de Imágenes
- [ ] Conversión automática a WebP
- [ ] Generación de múltiples tamaños
- [ ] Compresión inteligente
- [ ] Integración con CDN

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

## Próximos Pasos

1. **Auditoría actual**: Analizar estado actual de seguridad y rendimiento
2. **Implementación Fase 1**: Security Settings unificado
3. **Testing**: Validar mejoras en Core Web Vitals
4. **Iteración**: Ajustar basado en resultados
5. **Fase 2**: Optimizaciones avanzadas

---

*Este roadmap es un documento vivo que se actualizará conforme avance el proyecto.*
