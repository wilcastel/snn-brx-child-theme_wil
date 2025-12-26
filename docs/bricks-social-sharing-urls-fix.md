# Corrección de URLs del Widget Social Sharing de Bricks

## 📋 Descripción

Solución que corrige automáticamente las URLs de compartir del widget nativo "Social Sharing" de Bricks Builder, sin necesidad de reemplazar el widget ni crear elementos personalizados.

## 🎯 Problema Resuelto

El widget nativo de Bricks Builder "Social Sharing" generaba URLs incorrectas que causaban:
- **WhatsApp**: No cargaba la imagen destacada correctamente
- **Twitter móvil**: Mostraba error "El término que has introducido no ha obtenido ningún resultado"
- **URLs incorrectas**: No usaba las URLs correctas para compartir en cada plataforma

## ✅ Solución Implementada

Se agregó un script JavaScript en `includes/security-optimization.php` que:
- ✅ Detecta automáticamente los enlaces del widget Social Sharing de Bricks
- ✅ Corrige las URLs para todas las plataformas (Facebook, Twitter, WhatsApp, Telegram, Email)
- ✅ Mantiene toda la funcionalidad y estilos del widget nativo
- ✅ No requiere cambios en Bricks Builder
- ✅ Funciona automáticamente en todas las páginas

## 🔧 Cómo Funciona

El script se ejecuta en el `wp_footer` y:

1. **Detecta los enlaces** del widget Social Sharing de Bricks usando selectores CSS
2. **Identifica la plataforma** basándose en la URL del enlace
3. **Reemplaza la URL** con la correcta para cada plataforma:
   - **Facebook**: `https://www.facebook.com/sharer/sharer.php?u={URL}`
   - **Twitter/X**: `https://twitter.com/intent/tweet?url={URL}&text={TITLE}`
   - **WhatsApp**: `https://api.whatsapp.com/send?text={TITLE}%20{URL}`
   - **Telegram**: `https://t.me/share/url?url={URL}&text={TITLE}`
   - **Email**: `mailto:?subject={TITLE}&body={EXCERPT}%0A%0A{URL}`

## 🚀 Uso

**No requiere configuración**. El script se ejecuta automáticamente en todas las páginas individuales (posts/páginas).

Simplemente:
1. Usa el widget nativo "Social Sharing" de Bricks Builder
2. Configura las plataformas que quieras mostrar
3. El script corregirá automáticamente las URLs

## 🔗 URLs Corregidas

### Facebook
```
https://www.facebook.com/sharer/sharer.php?u={URL}
```
- Funciona en móvil y desktop
- Abre el compartidor web de Facebook

### Twitter/X
```
https://twitter.com/intent/tweet?url={URL}&text={TITLE}
```
- Funciona en móvil y desktop
- En móvil, abre la app si está instalada, o la web si no
- **Soluciona el error** "El término que has introducido no ha obtenido ningún resultado"

### WhatsApp
```
https://api.whatsapp.com/send?text={TITLE}%20{URL}
```
- Funciona en móvil (abre la app) y desktop (abre WhatsApp Web)
- Usa las meta tags Open Graph para la imagen (que ya están correctamente configuradas)

### Telegram
```
https://t.me/share/url?url={URL}&text={TITLE}
```
- Funciona en móvil (abre la app) y desktop (abre la web)

### Email
```
mailto:?subject={TITLE}&body={EXCERPT}%0A%0A{URL}
```
- Funciona en todos los dispositivos
- Incluye título, extracto y URL

## 🖼️ Imágenes para Compartir

Las imágenes se manejan mediante las **meta tags Open Graph** que ya están configuradas en `includes/security-optimization.php`:

- ✅ Convierte automáticamente WebP a JPG para WhatsApp
- ✅ Usa imagen destacada del post
- ✅ Fallback a imagen predefinida si no hay imagen destacada
- ✅ Dimensiones correctas (1200x630px)
- ✅ Incluye `og:image:secure_url` para HTTPS

**Nota**: Las meta tags Open Graph ya están correctamente configuradas. El script solo corrige las URLs de compartir del widget.

## 📱 Compatibilidad

- ✅ **Facebook**: Funciona en móvil y desktop
- ✅ **Twitter/X**: Funciona en móvil (app/web) y desktop
- ✅ **WhatsApp**: Funciona en móvil (app) y desktop (WhatsApp Web)
- ✅ **Telegram**: Funciona en móvil (app) y desktop (web)
- ✅ **Email**: Funciona en todos los dispositivos

## 🐛 Troubleshooting

### Las URLs no se corrigen

**Verifica que:**
1. El script esté cargándose (revisa el código fuente de la página, busca el script al final del `<body>`)
2. Los enlaces tengan las URLs originales de Bricks (el script las detecta por el dominio)
3. Estés en una página individual (post/página), no en archivo o página principal

### WhatsApp no carga la imagen

**Causa**: Las meta tags Open Graph deben estar correctas

**Solución**: 
1. Verifica las meta tags en el código fuente (busca `og:image`)
2. Usa [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/) para limpiar el cache
3. Verifica que la imagen no sea WebP (debe ser JPG/PNG)

### Twitter móvil sigue mostrando error

**Causa**: Cache del navegador o la app de Twitter

**Solución**:
1. Limpia el cache del navegador
2. Cierra y vuelve a abrir la app de Twitter
3. Verifica que la URL corregida sea `https://twitter.com/intent/tweet?url=...`

## 📝 Notas Técnicas

- El script se ejecuta en `wp_footer` solo en páginas individuales (`is_singular()`)
- Usa `querySelectorAll` para encontrar todos los enlaces de compartir
- Reemplaza las URLs antes de que el usuario haga clic
- Compatible con cualquier widget de Bricks que genere enlaces de compartir
- No interfiere con otros scripts o plugins

## 🔗 Ver También

- [Open Graph Social Sharing Fix](./open-graph-social-sharing-fix.md) - Guía completa sobre meta tags Open Graph
- [GTM Bricks First Party Cookies](./gtm-bricks-first-party-cookies.md) - Configuración de Google Tag Manager

