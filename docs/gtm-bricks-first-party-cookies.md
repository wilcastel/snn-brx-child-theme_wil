# Google Tag Manager en Bricks con Cookies de Primera Parte

## Problema

Cuando se configura Google Tag Manager (GTM) directamente en Bricks Settings, las cookies de terceros no funcionan correctamente y Lighthouse señala el uso de cookies de terceros. Esto puede afectar la privacidad y el cumplimiento de GDPR.

## Solución: Google Analytics 4 con Consent Mode v2 y Cookies de Primera Parte

### Recomendación Inicial

Para la mayoría de los casos, **no necesitas Server-Side Tagging**. La solución recomendada es:

1. ✅ Configurar GTM en Bricks (Paso 1)
2. ✅ Configurar cookies en la etiqueta GA4 (Paso 2)
3. ✅ Habilitar Consent Mode v2 (Paso 2)
4. ✅ Habilitar resumen de consentimiento en el contenedor (Paso 3)

Esta configuración reduce significativamente las advertencias de Lighthouse sobre cookies de terceros. Server-Side Tagging (Paso 4) solo es necesario si necesitas eliminar completamente las cookies de terceros, lo cual requiere configuración avanzada de servidor.

### Paso 1: Configurar GTM en Bricks Settings

1. Ve a **Bricks > Settings > Header scripts**
2. Agrega el siguiente código (reemplaza `GTM-XXXXXX` con tu ID de GTM):

```html
<!-- Google Tag Manager -->
<script>
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-XXXXXX');
</script>
<!-- End Google Tag Manager -->
```

3. Ve a **Bricks > Settings > Body (header) scripts**
4. Agrega el código noscript:

```html
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXX"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
```

### Paso 2: Configurar Google Analytics 4 en GTM con Cookies de Primera Parte

1. **En Google Tag Manager**, ve a tu contenedor
2. Crea o edita tu etiqueta **"Google Analytics: GA4 Configuration"**
3. En **"Más configuraciones"** > **"Campos para configurar"**, agrega estos campos:

```
cookie_flags: SameSite=None;Secure
cookie_update: true
cookie_expires: 63072000
cookie_domain: auto
```

4. **Habilitar Consent Mode v2**:
   - En la misma etiqueta, busca la sección **"Configuración de consentimiento (BETA)"**
   - Verás **"Comprobaciones de consentimiento integradas"** que muestra:
     - `ad_storage`
     - `ad_personalization`
     - `ad_user_data`
     - `analytics_storage`
   - En **"Comprobaciones de consentimiento adicionales"**, selecciona según tu necesidad:
     - **"No se requiere ningún consentimiento adicional"**: Si quieres que la etiqueta se active sin consentimiento explícito
     - **"La etiqueta requiere consentimiento adicional para activarse"**: Si quieres controlar manualmente el consentimiento

### Paso 3: Habilitar Resumen de Consentimiento en el Contenedor

Antes de configurar Server-Side Tagging, habilita el resumen de consentimiento:

1. **En Google Tag Manager**:
   - Ve a **Admin > Configuración del contenedor** (o **Container Settings**)
   - Se abrirá el diálogo **"Editar contenedor"**
   - En la sección **"Ajustes adicionales"**, marca la casilla:
     - ✅ **"Habilitar resumen de consentimiento (BETA)"**
   - Haz clic en **"Guardar"**

### Paso 4: Configurar Cookies de Primera Parte (Server-Side Tagging) - OPCIONAL

**NOTA IMPORTANTE**: Server-Side Tagging es una configuración avanzada que requiere:
- Una cuenta de Google Cloud Platform
- Configuración de un servidor de tagging
- Configuración de DNS para un subdominio

**Si solo quieres reducir las advertencias de Lighthouse**, el Consent Mode v2 (Paso 2) con la configuración de cookies del Paso 2 puede ser suficiente.

**Si quieres implementar Server-Side Tagging**:

1. **En Google Tag Manager**:
   - Ve a **Admin > Container Settings**
   - En el diálogo **"Editar contenedor"**, verás la configuración básica del contenedor
   - **Server Container NO aparece aquí** - es una funcionalidad separada
   
2. **Para crear un Server Container**:
   - Ve a **Admin** (en la parte superior de GTM)
   - Busca **"Server Containers"** o **"Tagging Server"** en el menú
   - Si no aparece, necesitas:
     - Tener una cuenta de Google Cloud Platform activa
     - Habilitar la API de Server-Side Tagging
     - Crear un nuevo Server Container desde cero

3. **Alternativa más simple**:
   - Si Server-Side Tagging no está disponible, usa la **Alternativa con Consent Mode v2** (ver más abajo)
   - Esta solución reduce significativamente las advertencias de Lighthouse sin necesidad de configuración de servidor

2. **Configurar subdominio para Server-Side Tagging**:
   - Crea un subdominio (ej: `gtm.tudominio.com`)
   - Configura DNS para apuntar al servidor de tagging de Google
   - En GTM, configura la URL del servidor

3. **Actualizar código en Bricks**:
   - Reemplaza la URL de GTM en el código del Paso 1 con la URL de tu servidor de tagging

### Paso 5: Verificar Configuración

1. **Verificar cookies en el navegador**:
   - Abre DevTools > Application > Cookies
   - Verifica que las cookies de GA4 estén en tu dominio (no en `google-analytics.com`)
   - Las cookies deberían tener nombres como `_ga`, `_ga_XXXXXXXXXX`

2. **Verificar en Lighthouse**:
   - Ejecuta Lighthouse en Chrome DevTools
   - Verifica que no aparezcan advertencias sobre cookies de terceros
   - Las cookies deberían aparecer como "First-party cookies"

3. **Verificar en Google Analytics**:
   - Ve a **Admin > Data Streams**
   - Verifica que los eventos se estén recibiendo correctamente

## Alternativa: Usar Consent Mode v2 sin Server-Side Tagging

Si no puedes configurar Server-Side Tagging, puedes usar **Consent Mode v2** que reduce el uso de cookies de terceros:

### Código actualizado para Bricks Header Scripts:

```html
<!-- Google Tag Manager con Consent Mode v2 -->
<script>
// Inicializar dataLayer y Consent Mode
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}

// Configurar Consent Mode v2 (por defecto: denegado)
gtag('consent', 'default', {
  'analytics_storage': 'denied',
  'ad_storage': 'denied',
  'wait_for_update': 500
});

// Cargar GTM
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-XXXXXX');
</script>
<!-- End Google Tag Manager -->
```

### Integración con Cookie Banner

Si tienes un banner de cookies, actualiza el consentimiento cuando el usuario acepta:

```javascript
// Cuando el usuario acepta cookies
gtag('consent', 'update', {
  'analytics_storage': 'granted',
  'ad_storage': 'granted'
});
```

## Notas Importantes

1. **Server-Side Tagging** es la solución más completa para cookies de primera parte, pero requiere configuración adicional del servidor.

2. **Consent Mode v2** reduce el uso de cookies de terceros pero no las elimina completamente. Es una solución intermedia.

3. **Verifica siempre** que la configuración funcione correctamente en diferentes navegadores y dispositivos.

4. **Lighthouse** puede seguir mostrando algunas advertencias si no usas Server-Side Tagging, pero Consent Mode v2 las reduce significativamente.

## Referencias

- [Google Tag Manager Server-Side Tagging](https://developers.google.com/tag-platform/tag-manager/server-side)
- [Google Analytics Consent Mode v2](https://developers.google.com/tag-platform/devguides/consent)
- [First-Party Cookies en GA4](https://support.google.com/analytics/answer/9304153)

