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
**Configuración**: `SNN Settings > Security & Optimization > Loading Optimization > Preload Critical Fonts`

#### Notas
- ⚠️ Esta opción está comentada/deprecada
- La funcionalidad se movió a `assets-optimization.php` para evitar duplicados
- El preload de fuentes se maneja desde la configuración de Assets Optimization

---

### 13-15. Keep Meta Tags (Essential, Social, SEO)
**Configuración**: `SNN Settings > Security & Optimization > Loading Optimization > Keep Essential/Social/SEO Meta`

#### Notas
- ⚠️ Estas opciones están definidas pero **NO están implementadas**
- Son opciones para mantener meta tags específicos cuando se hace limpieza del head
- Requieren implementación si se desea usar

---

## Próximos Pasos
- [ ] Revisar configuraciones avanzadas (Advanced Head Cleanup) ✅
- [ ] Probar configuraciones de optimización de carga (en progreso)
- [ ] Verificar compatibilidad con plugins comunes

