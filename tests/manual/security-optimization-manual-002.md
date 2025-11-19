# Test Manual: Security & Optimization - Limpieza Avanzada del Head

**ID**: `security-optimization-manual-002`  
**Fecha**: [FECHA]  
**Tester**: [NOMBRE]  
**Estado**: ⏳ Pendiente

## Objetivo
Verificar que las configuraciones avanzadas de limpieza del head funcionan correctamente y mejoran Core Web Vitals.

## Precondiciones
- WordPress instalado y funcionando
- Tema SNN-BRX-WIL activo
- Usuario con permisos de administrador
- Herramientas: DevTools del navegador, View Page Source

## Configuraciones a Probar

### 1. Remove WP Generator
**Configuración**: `SNN Settings > Security & Optimization > Advanced Head Cleanup > Remove WP Generator`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar código fuente HTML (View Page Source)
4. Buscar `<meta name="generator" content="WordPress X.X.X">`
5. Verificar con DevTools > Elements

#### Resultado Esperado
- ✅ No aparece meta tag generator
- ✅ No se ejecuta `wp_generator` action
- ✅ Mejora en seguridad (no expone versión)

#### Evidencia
- [ ] Captura del código fuente (sin meta generator)
- [ ] Captura de DevTools Elements

---

### 2. Remove WLW Manifest
**Configuración**: `SNN Settings > Security & Optimization > Advanced Head Cleanup > Remove WLW Manifest`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar código fuente HTML
4. Buscar `<link rel="wlwmanifest" type="application/wlwmanifest+xml">`
5. Verificar que no hay requests a `wlwmanifest.xml`

#### Resultado Esperado
- ✅ No aparece enlace a wlwmanifest
- ✅ No hay requests a archivo manifest
- ✅ Reduce elementos innecesarios en head

#### Evidencia
- [ ] Captura del código fuente
- [ ] Captura de Network tab

---

### 3. Remove RSD Link
**Configuración**: `SNN Settings > Security & Optimization > Advanced Head Cleanup > Remove RSD Link`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar código fuente HTML
4. Buscar `<link rel="EditURI" type="application/rsd+xml">`
5. Verificar que no hay requests a `rsd.xml`

#### Resultado Esperado
- ✅ No aparece enlace RSD
- ✅ Reduce información expuesta sobre el sitio
- ✅ Mejora seguridad

#### Evidencia
- [ ] Captura del código fuente
- [ ] Verificación con herramienta de inspección

---

### 4. Remove Shortlink
**Configuración**: `SNN Settings > Security & Optimization > Advanced Head Cleanup > Remove Shortlink`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar código fuente HTML
4. Buscar `<link rel="shortlink" href="...">`
5. Verificar en diferentes tipos de páginas (post, page, archive)

#### Resultado Esperado
- ✅ No aparece enlace shortlink
- ✅ Funciona en todos los tipos de contenido
- ✅ Reduce elementos innecesarios

#### Evidencia
- [ ] Captura del código fuente (post)
- [ ] Captura del código fuente (page)
- [ ] Captura del código fuente (archive)

---

### 5. Remove WP JSON Links
**Configuración**: `SNN Settings > Security & Optimization > Advanced Head Cleanup > Remove WP JSON Links`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar código fuente HTML
4. Buscar `<link rel="https://api.w.org/" href="...">`
5. Verificar que no aparecen enlaces a API REST

#### Resultado Esperado
- ✅ No aparecen enlaces a API REST en head
- ✅ Reduce superficie de ataque
- ✅ Mejora seguridad

#### Evidencia
- [ ] Captura del código fuente
- [ ] Verificación con grep en HTML

---

### 6. Remove OEmbed Links
**Configuración**: `SNN Settings > Security & Optimization > Advanced Head Cleanup > Remove OEmbed Links`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar código fuente HTML
4. Buscar `<link rel="alternate" type="application/json+oembed">`
5. Verificar en páginas con contenido embebido

#### Resultado Esperado
- ✅ No aparecen enlaces oEmbed en head
- ✅ Reduce elementos innecesarios
- ✅ Funciona correctamente en todas las páginas

#### Evidencia
- [ ] Captura del código fuente
- [ ] Verificación en páginas con embeds

---

### 7. Remove DNS Prefetch
**Configuración**: `SNN Settings > Security & Optimization > Advanced Head Cleanup > Remove DNS Prefetch`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar código fuente HTML
4. Buscar `<link rel="dns-prefetch" href="...">`
5. Verificar que no hay prefetch innecesario

#### Resultado Esperado
- ✅ No aparecen enlaces dns-prefetch
- ✅ Reduce elementos en head
- ✅ Puede mejorar control sobre recursos externos

#### Evidencia
- [ ] Captura del código fuente
- [ ] Nota sobre impacto en rendimiento (si aplica)

---

### 8. Remove Emoji Scripts
**Configuración**: `SNN Settings > Security & Optimization > Advanced Head Cleanup > Remove Emoji Scripts`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar código fuente HTML
4. Buscar scripts de emojis
5. Verificar Network tab para requests de emojis
6. Verificar que no se cargan scripts inline de emojis

#### Resultado Esperado
- ✅ No se cargan scripts de emojis
- ✅ No hay requests a `wp-emoji-release.min.js`
- ✅ No hay scripts inline de emojis

#### Evidencia
- [ ] Captura del código fuente
- [ ] Captura de Network tab
- [ ] Verificación con DevTools

---

### 9. Remove WP Block Library
**Configuración**: `SNN Settings > Security & Optimization > Advanced Head Cleanup > Remove WP Block Library`

#### Pasos
1. Activar la opción
2. Guardar cambios
3. Verificar código fuente HTML
4. Buscar `<link rel="stylesheet" id="wp-block-library-css">`
5. Verificar Network tab para requests a `block-library`
6. Verificar que el sitio sigue funcionando correctamente

#### Resultado Esperado
- ✅ No se carga CSS de block library
- ✅ Reduce tamaño de página
- ✅ El sitio funciona correctamente (si no se usan bloques)

#### Evidencia
- [ ] Captura del código fuente
- [ ] Captura de Network tab (sin block-library.css)
- [ ] Verificación visual del sitio

---

## Verificación de Core Web Vitals

### Antes de Activar Optimizaciones
- [ ] LCP: _____ segundos
- [ ] FID: _____ ms
- [ ] CLS: _____
- [ ] Tamaño total de página: _____ KB
- [ ] Número de requests: _____

### Después de Activar Optimizaciones
- [ ] LCP: _____ segundos
- [ ] FID: _____ ms
- [ ] CLS: _____
- [ ] Tamaño total de página: _____ KB
- [ ] Número de requests: _____

### Mejora Observada
- [ ] Reducción de tamaño: _____ KB (_____ %)
- [ ] Reducción de requests: _____ (_____ %)
- [ ] Mejora en LCP: _____ segundos
- [ ] Mejora en CLS: _____

## Resultado General

- [ ] ✅ Todas las pruebas pasaron
- [ ] ⚠️ Algunas pruebas tienen advertencias
- [ ] ❌ Algunas pruebas fallaron

## Notas Adicionales
[Espacio para notas, problemas encontrados, sugerencias de mejora]

## Próximos Pasos
- [ ] Probar configuraciones de optimización de carga
- [ ] Verificar compatibilidad con plugins
- [ ] Probar en diferentes tipos de contenido

