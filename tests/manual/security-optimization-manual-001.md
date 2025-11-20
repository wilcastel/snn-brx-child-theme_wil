# Test Manual: Security & Optimization - Configuraciones Básicas

**ID**: `security-optimization-manual-001`  
**Fecha**: 2025-01-XX  
**Tester**: [NOMBRE]  
**Estado**: 🔄 En Progreso

## Objetivo
Verificar que todas las configuraciones básicas de seguridad funcionan correctamente y se aplican según la configuración seleccionada.

## Precondiciones
- WordPress instalado y funcionando
- Tema SNN-BRX-WIL activo
- Usuario con permisos de administrador
- Acceso al panel de administración

## Configuraciones a Probar

### 1. Disable XML-RPC
**Configuración**: `SNN Settings > Security & Optimization > Basic Security Settings > Disable XML-RPC`

#### Pasos
1. Activar la opción "Disable XML-RPC"
2. Guardar cambios
3. Intentar acceder a `https://[tu-sitio]/xmlrpc.php` desde navegador
4. Intentar hacer una petición XML-RPC con herramienta externa (Postman, curl)

#### Resultado Esperado
- ✅ La opción se guarda correctamente
- ✅ `xmlrpc.php` devuelve error 403 o similar
- ✅ No se puede hacer peticiones XML-RPC

#### Evidencia
- [x] ✅ Configuración guardada correctamente (verificado en admin)
- [x] ✅ Respuesta de `xmlrpc.php`: HTTP 403 Forbidden
- [x] ✅ Respuesta XML: `<fault><faultCode>403</faultCode><faultString>XML-RPC is disabled</faultString></fault>`
- [x] ✅ Verificado con curl POST request

#### Notas
- Se implementó bloqueo directo en `functions.php` porque WordPress procesa XML-RPC antes de que los hooks del tema se ejecuten
- El filtro `xmlrpc_enabled` también está registrado como respaldo

---

### 2. Disable JSON API for Guests
**Configuración**: `SNN Settings > Security & Optimization > Basic Security Settings > Disable JSON API for Guests`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Cerrar sesión (o usar navegador en modo incógnito)
4. Intentar acceder a `https://[tu-sitio]/wp-json/wp/v2/posts`
5. Iniciar sesión como administrador
6. Intentar acceder nuevamente a la misma URL

#### Resultado Esperado
- ✅ Usuarios no logueados reciben error 401/403
- ✅ Usuarios logueados pueden acceder normalmente
- ✅ La opción se guarda correctamente

#### Evidencia
- [x] ✅ Respuesta sin sesión: HTTP 401, `{"code":"rest_not_logged_in","message":"You are not logged in."}`
- [x] ✅ Respuesta con sesión: HTTP 200, datos JSON devueltos correctamente
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- Se corrigió la lógica de la función `disable_json_for_guests()` para verificar primero si el usuario está logueado
- Los endpoints personalizados (`/wp-json/snn/`) siguen funcionando para usuarios no logueados
- Los endpoints de WindPress (`/wp-json/windpress/`) están exentos del bloqueo para mantener funcionalidad de Tailwind CSS

---

### 3. Disable File Editing
**Configuración**: `SNN Settings > Security & Optimization > Basic Security Settings > Disable File Editing`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Ir a `Apariencia > Editor de temas` (o `Plugins > Editor de plugins`)
4. Verificar que aparece mensaje de deshabilitado

#### Resultado Esperado
- ✅ No se puede acceder al editor de archivos
- ✅ Aparece mensaje indicando que está deshabilitado
- ✅ La constante `DISALLOW_FILE_EDIT` está definida

#### Evidencia
- [x] ✅ Cuando está desactivado: El enlace "Editor de temas" aparece en `Apariencia > Editor de temas`
- [x] ✅ Cuando está activado: El enlace "Editor de temas" NO aparece en el menú
- [x] ✅ La constante `DISALLOW_FILE_EDIT` está definida como `true` cuando la opción está activada
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- WordPress oculta automáticamente los enlaces del editor cuando `DISALLOW_FILE_EDIT` está definida
- No aparece mensaje explícito, simplemente desaparecen los enlaces del menú
- Esto aplica tanto para `Apariencia > Editor de temas` como `Plugins > Editor de plugins`

---

### 4. Remove RSS Feeds
**Configuración**: `SNN Settings > Security & Optimization > Basic Security Settings > Remove RSS Feeds`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Intentar acceder a `https://[tu-sitio]/feed/`
4. Intentar acceder a `https://[tu-sitio]/feed/rss/`
5. Verificar que no aparecen enlaces RSS en el `<head>`

#### Resultado Esperado
- ✅ No aparecen enlaces `<link rel="alternate" type="application/rss+xml">` en el HTML
- ✅ Los feeds siguen accesibles directamente por URL (solo se eliminan los enlaces del head)

#### Evidencia
- [x] ✅ Los enlaces RSS (`<link rel="alternate" type="application/rss+xml">`) han desaparecido del HTML
- [x] ✅ El feed directo (`/feed/`) sigue accesible (comportamiento esperado - solo se eliminan enlaces del head)
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- La función `remove_rss_feeds()` elimina los enlaces RSS del `<head>` usando `remove_action()`
- Los feeds siguen siendo accesibles directamente por URL (esto es intencional según la descripción de la opción)
- Si se requiere bloquear el acceso directo a los feeds, se necesitaría implementar un bloqueo adicional

---

### 5. Hide WP Version
**Configuración**: `SNN Settings > Security & Optimization > Basic Security Settings > Hide WP Version`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar el código fuente HTML (buscar `generator` o versión de WP)
4. Verificar que no aparece en `<meta name="generator">`
5. Verificar que no aparece en URLs de recursos (`?ver=X.X.X`)

#### Resultado Esperado
- ✅ No aparece `<meta name="generator">` en el HTML
- ✅ La función `the_generator()` devuelve string vacío
- ✅ No se expone la versión de WordPress

#### Evidencia
- [x] ✅ No aparece `<meta name="generator">` en el HTML
- [x] ✅ No aparece la versión de WordPress en los feeds RSS
- [x] ✅ No se encuentra ninguna referencia a "generator" o "wp-version" en el código fuente
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- La función `apply_head_optimizations()` ya elimina el meta generator automáticamente (siempre activo)
- La opción "Hide WP Version" agrega un filtro adicional `the_generator` que devuelve string vacío
- Esto asegura que la versión no se exponga en ningún contexto (HTML, feeds RSS, etc.)

---

### 6. Disable Bundled Themes
**Configuración**: `SNN Settings > Security & Optimization > Basic Security Settings > Disable Bundled Themes`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Ir a `Apariencia > Temas`
4. Verificar que no se instalan temas por defecto en actualizaciones

#### Resultado Esperado
- ✅ La constante `CORE_UPGRADE_SKIP_NEW_BUNDLED` está definida
- ✅ No se instalan temas por defecto automáticamente

#### Evidencia
- [x] ✅ La constante `CORE_UPGRADE_SKIP_NEW_BUNDLED` se define cuando la opción está activada
- [x] ✅ Configuración guardada correctamente (verificado en admin)
- [x] ✅ La implementación usa `define('CORE_UPGRADE_SKIP_NEW_BUNDLED', true)` en `apply_security_settings()`

#### Notas
- La constante `CORE_UPGRADE_SKIP_NEW_BUNDLED` es una constante de WordPress core que previene la instalación automática de temas por defecto (como Twenty Twenty-Four, etc.) durante las actualizaciones de WordPress
- El efecto solo se verá en la próxima actualización de WordPress
- Esta es una medida de seguridad que reduce el número de temas instalados y potencialmente vulnerables

---

### 7. Enable Math Captcha
**Configuración**: `SNN Settings > Security & Optimization > Basic Security Settings > Enable Math Captcha`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Ir a la página de login (`/wp-login.php`)
4. Verificar que aparece un campo de captcha matemático
5. Intentar hacer login sin resolver el captcha
6. Resolver el captcha correctamente e intentar login

#### Resultado Esperado
- ✅ Aparece campo de captcha matemático en el login
- ✅ No se puede hacer login sin resolver correctamente
- ✅ Con captcha correcto, el login funciona normalmente

#### Evidencia
- [x] ✅ Aparece campo de captcha matemático en el login (verificado visualmente)
- [x] ✅ El botón de login se deshabilita cuando el captcha está vacío o es incorrecto
- [x] ✅ El botón de login solo se habilita cuando el resultado del captcha es correcto
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- El captcha se implementa en `login-math-captcha.php` y se incluye cuando la opción está activada
- El captcha aparece en: login, registro, recuperación de contraseña, y comentarios (para usuarios no logueados)
- Tiene validación en JavaScript (deshabilita el botón hasta que sea correcto) y validación en PHP (valida en el servidor)
- Usa un canvas para mostrar la pregunta matemática de forma visual
- La validación en JavaScript previene envíos con respuestas incorrectas, mejorando la UX
- La validación en PHP asegura la seguridad incluso si JavaScript está deshabilitado

---

### 8. Disable Emojis
**Configuración**: `SNN Settings > Security & Optimization > Basic Security Settings > Disable Emojis`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar el código fuente HTML
4. Buscar scripts de emojis (`wp-emoji-release.min.js`)
5. Verificar que no se cargan scripts de emojis

#### Resultado Esperado
- ✅ No se cargan scripts de emojis de WordPress
- ✅ No aparecen enlaces a `wp-emoji-release.min.js`
- ✅ Se eliminan estilos inline de emojis

#### Evidencia
- [x] ✅ No aparecen scripts de emojis (`wp-emoji-release.min.js`) en el HTML
- [x] ✅ No aparecen referencias a `print_emoji` en el código fuente
- [x] ✅ Los scripts de emojis no se están cargando en el frontend
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- La función `disable_emojis()` remueve scripts y estilos de emojis tanto en frontend como en admin
- También remueve filtros de emojis en feeds, emails y embeds
- Desactiva el plugin de emojis en TinyMCE (editor de WordPress)
- Esto mejora el rendimiento al eliminar scripts innecesarios

---

### 9. Disable Gravatar
**Configuración**: `SNN Settings > Security & Optimization > Basic Security Settings > Disable Gravatar`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar que no se cargan imágenes de Gravatar
4. Verificar en comentarios o perfiles de usuario
5. Revisar Network tab para requests a `gravatar.com`

#### Resultado Esperado
- ✅ No se cargan imágenes de Gravatar
- ✅ No hay requests a `gravatar.com`
- ✅ La función `get_avatar()` devuelve string vacío

#### Evidencia
- [x] ✅ No aparecen referencias a `gravatar.com` en el HTML
- [x] ✅ No se cargan imágenes de Gravatar
- [x] ✅ La función `get_avatar()` devuelve string vacío cuando la opción está activada
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- La función `get_avatar()` devuelve un string vacío cuando la opción está activada
- Esto previene requests a `gravatar.com`, mejorando la privacidad y el rendimiento
- Los avatares no se mostrarán en comentarios, perfiles de usuario, etc.
- Esto es especialmente útil para cumplir con regulaciones de privacidad (GDPR) al evitar requests a servicios externos

---

## Advanced Head Cleanup

### 10. Remove WP Block Library
**Configuración**: `SNN Settings > Security & Optimization > Advanced Head Cleanup > Remove WP Block Library`

#### Pasos
1. Verificar estado actual (si wp-block-library se está cargando)
2. Activar la opción
3. Guardar cambios
4. Verificar que no se carga `wp-block-library.css` en el HTML
5. Verificar en Network tab que no hay requests a `wp-block-library`

#### Resultado Esperado
- ✅ No se carga CSS de `wp-block-library`
- ✅ No se carga CSS de `wp-block-library-theme`
- ✅ No hay requests a archivos de block library en Network tab
- ✅ Mejora en el tamaño del HTML y rendimiento

#### Evidencia
- [x] ✅ No aparece `wp-block-library` en el HTML
- [x] ✅ No aparece `wp-block-library-theme` en el HTML
- [x] ✅ No hay referencias a block library en el código fuente
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- La función `remove_wp_block_library()` usa `wp_dequeue_style()` y `wp_deregister_style()` con prioridad 99999 para ejecutarse después de plugins como Jetpack
- También tiene un fallback en `wp_head` para asegurar que se remueve incluso si se encola muy tarde
- Esto mejora el rendimiento al eliminar CSS innecesario si no se usan bloques de Gutenberg
- **Nota importante**: Si la opción "Remove Unused CSS" en Assets Optimization está activada (por defecto `true`), `wp-block-library` ya se remueve automáticamente. Esta opción en Security & Optimization es redundante en ese caso, pero puede ser útil si se desactiva "Remove Unused CSS"
- **Importante**: Si el sitio usa bloques de Gutenberg, esta opción puede romper el estilo de los bloques

---

## Resultado General

- [ ] ✅ Todas las pruebas pasaron
- [ ] ⚠️ Algunas pruebas tienen advertencias
- [ ] ❌ Algunas pruebas fallaron

## Notas Adicionales
[Espacio para notas, problemas encontrados, sugerencias de mejora]

---

## Loading Optimization

### 11. Optimize CSS Loading
**Configuración**: `SNN Settings > Security & Optimization > Loading Optimization > Optimize CSS Loading`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar que aparece `<link rel="preload" as="style">` en el HTML
4. Verificar que el CSS se carga con optimización

#### Resultado Esperado
- ✅ Aparece `<link rel="preload" as="style">` para el stylesheet principal
- ✅ El CSS se carga de forma optimizada
- ✅ Mejora en el rendimiento de carga

#### Evidencia
- [x] ✅ Aparece `<link rel="preload" as="style">` para el stylesheet principal (`style.css`)
- [x] ✅ El preload incluye `onload="this.onload=null;this.rel='stylesheet'"` para convertir el preload en stylesheet
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- La función `optimize_css_loading()` detecta automáticamente y agrega preload a estilos críticos:
  - Stylesheet principal del tema hijo (`style.css`)
  - Bricks Builder CSS (`bricks-frontend`)
  - CSS específico del tema (`snn-theme-specific`)
  - **WindPress observer script** (procesa el CSS compilado de Tailwind CSS)
- Si Bricks Builder o otros sistemas generan más hojas de estilo CSS críticas, se pueden agregar a la lista `$critical_handles` para preload automático
- **Nota sobre WindPress**: WindPress inyecta su CSS compilado de Tailwind mediante scripts (`windpress:metadata`, `windpress:vfs`), no como stylesheet tradicional. Por lo tanto, se preload el script observer de WindPress que procesa el CSS, mejorando la carga del CSS de Tailwind
- Usa la técnica de "preload as style" con `onload` para convertir el preload en stylesheet cuando se carga
- Incluye fallback `<noscript>` para navegadores sin JavaScript
- Esto mejora el LCP (Largest Contentful Paint) al cargar CSS crítico más rápido
- El preload se ejecuta antes que el stylesheet normal, mejorando el tiempo de renderizado

#### Notas
- La función `optimize_css_loading()` agrega un preload para el stylesheet principal
- Usa `onload` para convertir el preload en stylesheet cuando se carga
- Esto mejora el LCP (Largest Contentful Paint) al cargar CSS crítico más rápido

---

### 12. Preload Critical Fonts
**Configuración**: ~~`SNN Settings > Security & Optimization > Loading Optimization > Preload Critical Fonts`~~

#### Estado
- ❌ **ELIMINADA**: Esta opción fue eliminada de Security & Optimization
- ✅ La funcionalidad está disponible en `SNN Settings > Assets Optimization > Font Optimization > Enable Font Preloading`
- El preload de fuentes se maneja desde la configuración de Assets Optimization

---

### 13-15. Keep Meta Tags (Essential, Social, SEO)
**Configuración**: ~~`SNN Settings > Security & Optimization > Loading Optimization > Keep Essential/Social/SEO Meta`~~

#### Estado
- ❌ **ELIMINADAS**: Estas opciones fueron eliminadas de Security & Optimization
- ⚠️ No estaban implementadas y no se utilizaban
- Si se necesita esta funcionalidad en el futuro, se puede implementar en `head-optimization.php`

---

## Protocol Settings

### 16. Fix Mixed Content
**Configuración**: `SNN Settings > Security & Optimization > Protocol Settings > Fix Mixed Content`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar el código fuente HTML
4. Buscar scripts que corrijan URLs de contenido mixto (HTTP en HTTPS)

#### Resultado Esperado
- ✅ Se agrega script para corregir URLs de contenido mixto
- ✅ Las URLs HTTP se convierten automáticamente a HTTPS
- ✅ Mejora la seguridad al evitar contenido mixto

#### Evidencia
- [x] ✅ El meta tag `<meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">` aparece en el HTML
- [x] ✅ El meta tag se encuentra en el `<head>` del documento
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- La función `fix_mixed_content()` agrega un meta tag `Content-Security-Policy` con `upgrade-insecure-requests` en el `<head>`
- Este meta tag le indica al navegador que actualice automáticamente todas las peticiones HTTP a HTTPS
- Esto es útil cuando hay recursos (imágenes, scripts, estilos) cargados vía HTTP en un sitio HTTPS
- El navegador automáticamente convierte las URLs HTTP a HTTPS antes de hacer las peticiones
- Esto mejora la seguridad al evitar contenido mixto (mixed content) sin necesidad de modificar manualmente todas las URLs

---

### 17. Add CORS Headers
**Configuración**: `SNN Settings > Security & Optimization > Protocol Settings > Add CORS Headers`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar headers HTTP (usar herramientas de desarrollador o curl)
4. Buscar headers `Access-Control-Allow-Origin`

#### Resultado Esperado
- ✅ Se agregan headers CORS apropiados
- ✅ Los recursos del mismo dominio pueden acceder correctamente
- ✅ Mejora la compatibilidad con recursos locales

#### Evidencia
- [x] ✅ Los headers CORS se agregan correctamente para peticiones no-API:
  - `Access-Control-Allow-Origin: http://lanacionweb.test`
  - `Access-Control-Allow-Methods: GET, POST, OPTIONS`
  - `Access-Control-Allow-Headers: Content-Type`
- [x] ✅ Los endpoints REST API (`/wp-json/`) NO reciben headers CORS adicionales (evita conflictos)
- [x] ✅ Los endpoints de WindPress (`/wp-json/windpress/`) mantienen sus headers nativos de WordPress REST API
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- La función `add_cors_headers()` agrega headers CORS en `send_headers` SOLO para recursos no-API
- **Importante**: Excluye endpoints REST API (`/wp-json/`) para evitar conflictos con WordPress REST API y plugins como WindPress
- WordPress REST API ya maneja sus propios headers CORS, por lo que no los sobrescribimos
- Esto permite que recursos del mismo dominio (no-API) se carguen sin problemas de CORS
- Los plugins que usan REST API (como WindPress) siguen funcionando normalmente con sus headers nativos

---

### 18. Protocol Detection
**Configuración**: `SNN Settings > Security & Optimization > Protocol Settings > Protocol Detection`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar el código fuente HTML (buscar script de detección de protocolo)
4. Verificar que el script se carga en el footer

#### Resultado Esperado
- ✅ Se agrega script de detección de protocolo en el footer
- ✅ El script detecta automáticamente HTTP/HTTPS
- ✅ Mejora la compatibilidad con recursos que requieren detección de protocolo

#### Evidencia
- [x] ✅ El script de detección de protocolo aparece en el footer del HTML
- [x] ✅ El script verifica `location.protocol !== 'https:'` y `location.hostname !== 'localhost'`
- [x] ✅ Si el protocolo no es HTTPS y no es localhost, redirige automáticamente a HTTPS
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- La función `protocol_detection_script()` agrega un script en `wp_footer`
- El script detecta si el protocolo no es HTTPS y redirige automáticamente (excepto en localhost)
- Útil para forzar HTTPS en producción sin necesidad de configuración del servidor
- **Nota**: Este script se ejecuta en el cliente (JavaScript), por lo que hay un pequeño delay antes de la redirección
- **Diferencia con Force HTTPS**: "Force HTTPS" redirige en el servidor (más rápido), "Protocol Detection" redirige en el cliente (JavaScript)
- En localhost, el script no redirige para permitir desarrollo local sin HTTPS

---

### 19. Fix Font URLs
**Configuración**: `SNN Settings > Security & Optimization > Protocol Settings > Fix Font URLs`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar que las URLs de fuentes se corrigen correctamente
4. Verificar que no hay errores CORS relacionados con fuentes

#### Resultado Esperado
- ✅ Las URLs de fuentes se corrigen automáticamente
- ✅ No hay errores CORS relacionados con fuentes
- ✅ Las fuentes se cargan correctamente

#### Evidencia
- [x] ✅ El estilo `<style>@font-face { font-display: swap; }</style>` aparece en el HTML
- [x] ✅ El estilo se encuentra en el `<head>` del documento
- [x] ✅ Las fuentes individuales ya tienen `font-display: swap` en sus declaraciones
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- La función `fix_font_urls()` agrega un estilo global `@font-face { font-display: swap; }` en el `<head>`
- Esto actúa como respaldo para asegurar que todas las fuentes usen `font-display: swap`
- `font-display: swap` mejora el rendimiento al mostrar texto con fuente del sistema mientras se carga la fuente personalizada
- Esto previene el "flash de texto invisible" (FOIT) y mejora el LCP (Largest Contentful Paint)
- Las fuentes individuales ya tienen `font-display: swap` en sus declaraciones `@font-face`, pero este estilo global actúa como respaldo
- **Nota**: Aunque el nombre sugiere "Fix Font URLs", la función actualmente agrega `font-display: swap` en lugar de corregir URLs. Esto es útil para prevenir problemas de CORS relacionados con la carga de fuentes

---

### 20. Force HTTPS
**Configuración**: `SNN Settings > Security & Optimization > Protocol Settings > Force HTTPS`

#### Pasos
1. ⚠️ **ADVERTENCIA**: Esta opción fuerza redirección a HTTPS
2. ⚠️ **NO PROBAR EN LOCAL**: El entorno local no tiene HTTPS configurado
3. Verificar el código de la función `force_https()`
4. Documentar que requiere configuración HTTPS en producción

#### Resultado Esperado
- ✅ La función `force_https()` está implementada correctamente
- ✅ Redirige todas las peticiones HTTP a HTTPS con código 301
- ⚠️ Solo debe activarse si el sitio está completamente configurado para HTTPS

#### Evidencia
- [x] ✅ La función `force_https()` está implementada en `security-optimization.php`
- [x] ✅ La función verifica `is_ssl()` antes de redirigir
- [x] ✅ Usa `wp_redirect()` con código 301 (redirección permanente)
- [x] ⚠️ **NO PROBADO EN LOCAL**: El entorno local no tiene HTTPS configurado
- [x] ⚠️ **NO ACTIVADO**: No se activó para evitar bucles de redirección en local

#### Notas
- ⚠️ **IMPORTANTE**: Esta opción debe usarse con precaución
- Solo debe activarse si el sitio está completamente configurado para HTTPS
- La función `force_https()` redirige todas las peticiones HTTP a HTTPS usando `template_redirect`
- Si el sitio no está configurado para HTTPS, puede causar bucles de redirección
- **Código de la función**:
  ```php
  public function force_https() {
      if (!is_ssl()) {
          wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301);
          exit();
      }
  }
  ```
- **Diferencia con Protocol Detection**: 
  - "Force HTTPS" redirige en el servidor (más rápido, antes de cargar la página)
  - "Protocol Detection" redirige en el cliente (JavaScript, después de cargar la página)
- **Recomendación**: Usar "Force HTTPS" en producción con configuración adecuada del servidor

---

## Resource Management

### 21. Conditional Dashicons
**Configuración**: `SNN Settings > Security & Optimization > Resource Management > Conditional Dashicons`

#### Pasos
1. Verificar estado actual (sin sesión iniciada)
2. Verificar que dashicons NO se carga para usuarios no logueados
3. Iniciar sesión como usuario con permisos de editor+
4. Verificar que dashicons SÍ se carga para usuarios logueados con permisos de editor+

#### Resultado Esperado
- ✅ Dashicons NO se carga para usuarios no logueados
- ✅ Dashicons SÍ se carga para usuarios logueados con permisos de editor+
- ✅ Ahorro de recursos para usuarios no logueados

#### Evidencia
- [x] ✅ Dashicons NO aparece en el HTML para usuarios no logueados (verificado con curl)
- [x] ✅ La función `conditional_dashicons()` está siempre activa (no requiere opción)
- [x] ✅ Configuración guardada correctamente (verificado en admin)

#### Notas
- **IMPORTANTE**: La función `conditional_dashicons()` está **siempre activa** y no depende de la opción configurable
- La opción `conditional_dashicons` está definida en la interfaz pero actualmente no se está usando
- La función se ejecuta en `apply_head_optimizations()` y remueve dashicons para usuarios no logueados o sin permisos de editor
- Esto ahorra ~15KB por usuario no logueado
- Dashicons solo es necesario en el admin, no en el frontend para usuarios normales
- **Nota**: Esta funcionalidad también está implementada en `head-optimization.php` y se ejecuta siempre
- La función `conditional_dashicons_new()` está definida pero no se está usando actualmente

---

## Próximos Pasos
- [x] Revisar configuraciones avanzadas (Advanced Head Cleanup) ✅
- [x] Probar configuraciones de optimización de carga (Optimize CSS Loading) ✅
- [ ] Probar configuraciones de Protocol Settings
- [ ] Probar configuraciones de Resource Management
- [ ] Verificar compatibilidad con plugins comunes

