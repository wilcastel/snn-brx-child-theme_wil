# Test Manual: Head Optimization - Optimizaciones Automáticas

**ID**: `head-optimization-manual-001`  
**Fecha**: [FECHA]  
**Tester**: [NOMBRE]  
**Estado**: ⏳ Pendiente

## Objetivo
Verificar que las optimizaciones automáticas del head funcionan correctamente y mejoran Core Web Vitals sin necesidad de configuración manual.

## Precondiciones
- WordPress instalado y funcionando
- Tema SNN-BRX-WIL activo
- Herramientas: DevTools, Lighthouse, PageSpeed Insights

## Nota Importante
Estas optimizaciones están **siempre activas** según el código (`head-optimization.php` siempre se carga).

## Optimizaciones a Verificar

### 1. Meta Tags Esenciales
**Verificación**: Meta tags críticos deben estar presentes

#### Pasos
1. Abrir cualquier página del sitio
2. Ver código fuente HTML
3. Verificar presencia de:
   - `<meta charset="utf-8">`
   - `<meta name="viewport" content="width=device-width, initial-scale=1">`

#### Resultado Esperado
- ✅ Meta charset presente y correcto
- ✅ Meta viewport presente y correcto
- ✅ Ubicados al inicio del `<head>`

#### Evidencia
- [ ] Captura del código fuente (inicio del head)
- [ ] Verificación con DevTools

---

### 2. Meta Tags SEO
**Verificación**: Meta tags de SEO deben mantenerse

#### Pasos
1. Abrir una página o post
2. Ver código fuente HTML
3. Verificar presencia de:
   - `<link rel="canonical" href="...">` (si está configurado)
   - `<meta name="robots" content="...">` (si está configurado)

#### Resultado Esperado
- ✅ Meta tags SEO se mantienen
- ✅ No se eliminan por las optimizaciones
- ✅ Funcionan correctamente

#### Evidencia
- [ ] Captura del código fuente
- [ ] Verificación con herramienta SEO

---

### 3. Meta Tags Sociales
**Verificación**: Open Graph y Twitter Cards deben mantenerse

#### Pasos
1. Abrir una página o post
2. Ver código fuente HTML
3. Verificar presencia de:
   - `<meta property="og:title" content="...">`
   - `<meta property="og:description" content="...">`
   - `<meta name="twitter:card" content="...">`
   - (Si están configurados por plugins o tema)

#### Resultado Esperado
- ✅ Meta tags sociales se mantienen
- ✅ No se eliminan por las optimizaciones
- ✅ Funcionan correctamente en redes sociales

#### Evidencia
- [ ] Captura del código fuente
- [ ] Prueba con Facebook Debugger
- [ ] Prueba con Twitter Card Validator

---

### 4. Preload de Fuentes Críticas
**Verificación**: Fuentes críticas deben tener preload

#### Pasos
1. Abrir página principal
2. Ver código fuente HTML
3. Buscar `<link rel="preload" as="font" type="font/woff2">`
4. Verificar Network tab para carga de fuentes
5. Verificar que las fuentes críticas se cargan temprano

#### Resultado Esperado
- ✅ Fuentes críticas tienen preload
- ✅ Se cargan antes de ser necesarias
- ✅ Mejora LCP (Largest Contentful Paint)

#### Evidencia
- [ ] Captura del código fuente (preload links)
- [ ] Captura de Network tab (timing de fuentes)
- [ ] Verificación con Lighthouse

---

### 5. Optimización de CSS Loading
**Verificación**: CSS debe cargarse de forma optimizada

#### Pasos
1. Abrir página principal
2. Ver código fuente HTML
3. Verificar orden de carga de CSS
4. Verificar Network tab para timing de CSS
5. Verificar que CSS crítico se carga primero

#### Resultado Esperado
- ✅ CSS crítico se carga temprano
- ✅ CSS no crítico se carga de forma diferida
- ✅ Mejora en renderizado inicial

#### Evidencia
- [ ] Captura del código fuente (orden de CSS)
- [ ] Captura de Network tab (waterfall)
- [ ] Verificación con Lighthouse

---

### 6. Eliminación de Elementos Innecesarios
**Verificación**: Elementos innecesarios deben eliminarse automáticamente

#### Pasos
1. Abrir página principal
2. Ver código fuente HTML
3. Verificar que NO aparecen:
   - Enlaces a feeds RSS (si están deshabilitados)
   - Scripts de emojis (si están deshabilitados)
   - Meta generator (si está deshabilitado)
   - Otros elementos según configuración

#### Resultado Esperado
- ✅ Elementos innecesarios se eliminan
- ✅ Head más limpio
- ✅ Menor tamaño de página

#### Evidencia
- [ ] Captura del código fuente (head completo)
- [ ] Comparación antes/después

---

## Verificación de Core Web Vitals

### Métricas a Medir

#### LCP (Largest Contentful Paint)
- [ ] Valor medido: _____ segundos
- [ ] Objetivo: < 2.5 segundos
- [ ] Estado: ✅ Cumple / ❌ No cumple

#### FID (First Input Delay)
- [ ] Valor medido: _____ ms
- [ ] Objetivo: < 100 ms
- [ ] Estado: ✅ Cumple / ❌ No cumple

#### CLS (Cumulative Layout Shift)
- [ ] Valor medido: _____
- [ ] Objetivo: < 0.1
- [ ] Estado: ✅ Cumple / ❌ No cumple

### Herramientas de Medición
- [ ] Lighthouse (Chrome DevTools)
- [ ] PageSpeed Insights
- [ ] WebPageTest
- [ ] Chrome User Experience Report

### Capturas de Evidencia
- [ ] Captura de Lighthouse (Desktop)
- [ ] Captura de Lighthouse (Mobile)
- [ ] Captura de PageSpeed Insights
- [ ] Captura de Network tab (waterfall)

---

## Comparación Antes/Después

### Antes de Optimizaciones
- Tamaño total de página: _____ KB
- Número de requests: _____
- Tiempo de carga: _____ segundos
- LCP: _____ segundos
- CLS: _____

### Después de Optimizaciones
- Tamaño total de página: _____ KB
- Número de requests: _____
- Tiempo de carga: _____ segundos
- LCP: _____ segundos
- CLS: _____

### Mejora Observada
- Reducción de tamaño: _____ KB (_____ %)
- Reducción de requests: _____ (_____ %)
- Mejora en LCP: _____ segundos (_____ %)
- Mejora en CLS: _____ (_____ %)

---

## Verificación de Compatibilidad

### Plugins Comunes
- [ ] Yoast SEO - Funciona correctamente
- [ ] Rank Math - Funciona correctamente
- [ ] WooCommerce - Funciona correctamente
- [ ] Contact Form 7 - Funciona correctamente
- [ ] Otros plugins activos: _____

### Temas y Builders
- [ ] Bricks Builder - Funciona correctamente
- [ ] WindPress - Funciona correctamente
- [ ] Tailwind CSS - Funciona correctamente

---

## Resultado General

- [ ] ✅ Todas las pruebas pasaron
- [ ] ⚠️ Algunas pruebas tienen advertencias
- [ ] ❌ Algunas pruebas fallaron

## Notas Adicionales
[Espacio para notas, problemas encontrados, sugerencias de mejora]

## Próximos Pasos
- [ ] Probar en diferentes tipos de contenido
- [ ] Verificar en diferentes navegadores
- [ ] Probar con diferentes plugins activos
- [ ] Optimizar según resultados

