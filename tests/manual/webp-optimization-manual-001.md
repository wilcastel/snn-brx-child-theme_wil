# Test Manual: WebP Image Optimization

**ID**: `webp-optimization-manual-001`  
**Fecha**: [FECHA]  
**Tester**: [NOMBRE]  
**Estado**: ⏳ Pendiente

## Objetivo
Verificar que el sistema de optimización de imágenes WebP funciona correctamente, convierte imágenes automáticamente y mejora Core Web Vitals.

## Precondiciones
- WordPress instalado y funcionando
- Tema SNN-BRX-WIL activo
- Usuario con permisos de administrador
- Imágenes de prueba (JPG, PNG) para subir

## Configuración Inicial

### 1. Verificar Configuración
**Configuración**: `SNN Settings > WebP Image Optimization`

#### Pasos
1. Ir a `SNN Settings > WebP Image Optimization`
2. Verificar que la página de configuración carga correctamente
3. Revisar opciones disponibles:
   - [ ] Habilitar conversión automática
   - [ ] Calidad de compresión
   - [ ] Tamaños a generar
   - [ ] Otras opciones

#### Resultado Esperado
- ✅ Página de configuración carga correctamente
- ✅ Todas las opciones son accesibles
- ✅ Descripciones son claras

#### Evidencia
- [ ] Captura de pantalla de la página de configuración

---

## Pruebas de Conversión Automática

### 2. Subir Imagen JPG
**Escenario**: Subir una imagen JPG nueva

#### Pasos
1. Activar conversión automática a WebP (si no está activada)
2. Ir a `Medios > Añadir nuevo`
3. Subir una imagen JPG (tamaño recomendado: > 500KB)
4. Esperar a que se procese
5. Verificar en `Medios` que se creó versión WebP
6. Verificar en el servidor que existe archivo `.webp`

#### Resultado Esperado
- ✅ La imagen se sube correctamente
- ✅ Se genera automáticamente versión WebP
- ✅ El archivo WebP existe en el servidor
- ✅ El archivo WebP es más pequeño que el original

#### Evidencia
- [ ] Captura de pantalla de la biblioteca de medios (con WebP)
- [ ] Captura del sistema de archivos (archivo .webp)
- [ ] Comparación de tamaños (original vs WebP)

---

### 3. Subir Imagen PNG
**Escenario**: Subir una imagen PNG nueva

#### Pasos
1. Ir a `Medios > Añadir nuevo`
2. Subir una imagen PNG (tamaño recomendado: > 500KB)
3. Esperar a que se procese
4. Verificar que se creó versión WebP
5. Verificar que el archivo WebP existe

#### Resultado Esperado
- ✅ La imagen PNG se convierte a WebP
- ✅ El archivo WebP existe
- ✅ El archivo WebP es más pequeño

#### Evidencia
- [ ] Captura de pantalla de la biblioteca de medios
- [ ] Captura del sistema de archivos
- [ ] Comparación de tamaños

---

### 4. Conversión de Imágenes Existentes
**Escenario**: Convertir imágenes que ya están en el sitio

#### Pasos
1. Ir a `SNN Settings > WebP Image Optimization`
2. Buscar opción de "Convertir imágenes existentes" o similar
3. Ejecutar conversión masiva
4. Verificar progreso (si hay indicador)
5. Verificar que se crearon archivos WebP para imágenes existentes

#### Resultado Esperado
- ✅ Se pueden convertir imágenes existentes
- ✅ El proceso muestra progreso (si aplica)
- ✅ Se crean archivos WebP para imágenes existentes
- ✅ No se duplican conversiones

#### Evidencia
- [ ] Captura de pantalla del proceso de conversión
- [ ] Captura de archivos convertidos
- [ ] Log de conversión (si existe)

---

## Pruebas de Servicio de Imágenes

### 5. Servir WebP en Frontend
**Escenario**: Verificar que se sirven imágenes WebP en el frontend

#### Pasos
1. Crear o editar una página/post con una imagen
2. Publicar la página
3. Abrir la página en el navegador
4. Abrir DevTools > Network tab
5. Filtrar por "Img"
6. Verificar que las imágenes se cargan como WebP
7. Verificar headers de respuesta (`Content-Type: image/webp`)

#### Resultado Esperado
- ✅ Las imágenes se sirven como WebP
- ✅ Headers indican `Content-Type: image/webp`
- ✅ Navegadores compatibles reciben WebP
- ✅ Navegadores no compatibles reciben formato original

#### Evidencia
- [ ] Captura de Network tab (mostrando WebP)
- [ ] Captura de headers de respuesta
- [ ] Captura visual de la página (imágenes se ven correctamente)

---

### 6. Fallback para Navegadores Antiguos
**Escenario**: Verificar que navegadores antiguos reciben formato original

#### Pasos
1. Configurar User-Agent de navegador antiguo (o usar herramienta)
2. Abrir página con imágenes
3. Verificar que se sirven imágenes en formato original (JPG/PNG)
4. Verificar que no hay errores

#### Resultado Esperado
- ✅ Navegadores antiguos reciben formato original
- ✅ No hay errores de carga
- ✅ Las imágenes se ven correctamente

#### Evidencia
- [ ] Captura de Network tab (con formato original)
- [ ] Captura visual de la página

---

## Pruebas de Rendimiento

### 7. Impacto en Core Web Vitals
**Escenario**: Medir mejora en Core Web Vitals

#### Pasos
1. Medir Core Web Vitals ANTES de activar WebP:
   - LCP: _____ segundos
   - Tamaño total de imágenes: _____ KB
   - Número de imágenes: _____
2. Activar optimización WebP
3. Convertir imágenes existentes
4. Medir Core Web Vitals DESPUÉS:
   - LCP: _____ segundos
   - Tamaño total de imágenes: _____ KB
   - Número de imágenes: _____

#### Resultado Esperado
- ✅ Reducción en tamaño total de imágenes
- ✅ Mejora en LCP (si las imágenes son LCP element)
- ✅ Reducción en tiempo de carga

#### Evidencia
- [ ] Captura de Lighthouse (antes)
- [ ] Captura de Lighthouse (después)
- [ ] Comparación de métricas
- [ ] Captura de Network tab (comparación de tamaños)

---

### 8. Comparación de Tamaños
**Escenario**: Verificar reducción de tamaño de archivos

#### Pasos
1. Seleccionar 5-10 imágenes convertidas
2. Comparar tamaño original vs WebP:
   - Imagen 1: Original _____ KB, WebP _____ KB, Reducción _____ %
   - Imagen 2: Original _____ KB, WebP _____ KB, Reducción _____ %
   - Imagen 3: Original _____ KB, WebP _____ KB, Reducción _____ %
   - Imagen 4: Original _____ KB, WebP _____ KB, Reducción _____ %
   - Imagen 5: Original _____ KB, WebP _____ KB, Reducción _____ %
3. Calcular promedio de reducción

#### Resultado Esperado
- ✅ Reducción promedio significativa (>20-30%)
- ✅ Calidad visual aceptable
- ✅ Archivos WebP válidos

#### Evidencia
- [ ] Tabla comparativa de tamaños
- [ ] Capturas visuales (comparación de calidad)
- [ ] Promedio de reducción: _____ %

---

## Pruebas de Configuración

### 9. Diferentes Niveles de Calidad
**Escenario**: Probar diferentes niveles de compresión

#### Pasos
1. Configurar calidad baja (ej: 70)
2. Convertir imagen de prueba
3. Verificar tamaño y calidad
4. Configurar calidad alta (ej: 90)
5. Convertir misma imagen
6. Comparar tamaños y calidad

#### Resultado Esperado
- ✅ Calidad baja = menor tamaño, calidad aceptable
- ✅ Calidad alta = mayor tamaño, mejor calidad
- ✅ Usuario puede elegir balance calidad/tamaño

#### Evidencia
- [ ] Comparación visual de calidad
- [ ] Comparación de tamaños
- [ ] Nota sobre recomendación de calidad

---

### 10. Tamaños Responsive
**Escenario**: Verificar que se generan tamaños responsive

#### Pasos
1. Verificar configuración de tamaños de imagen
2. Subir imagen nueva
3. Verificar que se generan todos los tamaños (thumbnail, medium, large, etc.)
4. Verificar que cada tamaño tiene versión WebP

#### Resultado Esperado
- ✅ Se generan todos los tamaños configurados
- ✅ Cada tamaño tiene versión WebP
- ✅ Tamaños se usan correctamente en frontend

#### Evidencia
- [ ] Captura de archivos generados
- [ ] Verificación en frontend (srcset)

---

## Resultado General

- [ ] ✅ Todas las pruebas pasaron
- [ ] ⚠️ Algunas pruebas tienen advertencias
- [ ] ❌ Algunas pruebas fallaron

## Notas Adicionales
[Espacio para notas, problemas encontrados, sugerencias de mejora]

## Próximos Pasos
- [ ] Probar con diferentes tipos de imágenes
- [ ] Verificar en diferentes navegadores
- [ ] Probar con CDN (si aplica)
- [ ] Optimizar configuración según resultados

