# Elemento SNN Social Sharing Buttons

## 📋 Descripción

Elemento personalizado de Bricks Builder que reemplaza el widget nativo de Social Sharing con URLs mejoradas que funcionan correctamente en móvil y desktop para todas las plataformas.

## 🎯 Problema Resuelto

El widget nativo de Bricks Builder "Social Sharing" tenía problemas:
- **WhatsApp**: No cargaba la imagen destacada (problema con WebP)
- **Twitter móvil**: Intentaba abrir la app pero mostraba error "El término que has introducido no ha obtenido ningún resultado"
- **URLs incorrectas**: No usaba las URLs correctas para compartir en cada plataforma

## ✅ Solución Implementada

Este elemento personalizado:
- ✅ Usa URLs correctas para todas las plataformas
- ✅ Convierte automáticamente WebP a JPG para WhatsApp
- ✅ Funciona correctamente en móvil y desktop
- ✅ No requiere plugins adicionales
- ✅ Usa las meta tags Open Graph correctamente

## 🚀 Cómo Usar

### Paso 1: Agregar el Elemento

1. En el editor de Bricks, busca **"SNN Social Sharing"** en la lista de elementos
2. Arrástralo a tu template o página
3. El elemento aparecerá en la categoría **"SNN"**

### Paso 2: Configurar Plataformas

En la pestaña **"Content"**:
- **Social Platforms**: Selecciona qué plataformas mostrar (Facebook, Twitter/X, WhatsApp, Telegram, Email)
- **Direction**: Horizontal o Vertical
- **Use Brand Colors**: Activa para usar los colores oficiales de cada plataforma
- **Open in New Tab**: Abre los enlaces en una nueva pestaña

### Paso 3: Personalizar Estilo

En la pestaña **"Style"**:
- **Icon Size**: Tamaño de los iconos (por defecto: 24px)
- **Gap**: Espacio entre botones (por defecto: 12px)

## 🔗 URLs de Compartir Utilizadas

El elemento usa las siguientes URLs (correctas para móvil y desktop):

### Facebook
```
https://www.facebook.com/sharer/sharer.php?u={URL}
```

### Twitter/X
```
https://twitter.com/intent/tweet?url={URL}&text={TITLE}
```
**Nota**: Esta URL funciona en móvil y desktop. En móvil, abre la app si está instalada, o la versión web si no.

### WhatsApp
```
https://api.whatsapp.com/send?text={TITLE}%20{URL}
```
**Nota**: 
- En móvil, abre la app de WhatsApp si está instalada
- En desktop, abre WhatsApp Web
- Las imágenes WebP se convierten automáticamente a JPG

### Telegram
```
https://t.me/share/url?url={URL}&text={TITLE}
```

### Email
```
mailto:?subject={TITLE}&body={EXCERPT}%0A%0A{URL}
```

## 🖼️ Manejo de Imágenes

El elemento automáticamente:
1. Obtiene la imagen destacada del post
2. Si la imagen es WebP, la convierte a JPG para WhatsApp
3. Si no hay imagen destacada, usa la imagen predefinida: `favorit.jpg`
4. Usa las dimensiones correctas (1200x630px) para Open Graph

## 🔄 Reemplazar el Widget Nativo de Bricks

Para reemplazar el widget nativo:

1. **Elimina** el elemento "Social Sharing" nativo de Bricks
2. **Agrega** el elemento "SNN Social Sharing"
3. **Configura** las plataformas y estilos según tus necesidades

## 📱 Compatibilidad

- ✅ **Facebook**: Funciona en móvil y desktop
- ✅ **Twitter/X**: Funciona en móvil (abre app o web) y desktop
- ✅ **WhatsApp**: Funciona en móvil (abre app) y desktop (abre WhatsApp Web)
- ✅ **Telegram**: Funciona en móvil (abre app) y desktop (abre web)
- ✅ **Email**: Funciona en todos los dispositivos

## 🐛 Troubleshooting

### La imagen no aparece en WhatsApp

**Causa**: WhatsApp no soporta bien WebP para Open Graph

**Solución**: El elemento convierte automáticamente WebP a JPG. Verifica que:
1. La imagen original (JPG/PNG) exista en el servidor
2. Las meta tags Open Graph estén correctamente configuradas
3. Usa [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/) para limpiar el cache

### Twitter móvil muestra error

**Causa**: El widget nativo de Bricks usaba URLs incorrectas

**Solución**: Este elemento usa `https://twitter.com/intent/tweet` que funciona correctamente en móvil y desktop

### Los iconos no aparecen

**Causa**: Font Awesome no está cargado

**Solución**: Asegúrate de que Font Awesome esté habilitado en Bricks:
1. Ve a **Bricks > Settings > General**
2. Verifica que **"Load Font Awesome"** esté activado

## 📝 Notas Técnicas

- El elemento usa las meta tags Open Graph generadas por `includes/security-optimization.php`
- Las URLs se generan dinámicamente basándose en el post actual
- El elemento es compatible con todos los temas que usen Bricks Builder
- No requiere configuración adicional en WordPress

## 🔗 Ver También

- [Open Graph Social Sharing Fix](./open-graph-social-sharing-fix.md) - Guía completa sobre meta tags Open Graph
- [GTM Bricks First Party Cookies](./gtm-bricks-first-party-cookies.md) - Configuración de Google Tag Manager

