# Estructura de Pruebas

Este directorio centraliza pruebas manuales, de integración y (a futuro) E2E.

## Directorios
- `manual/`: listas de verificación y casos por módulo
- `integration/`: validaciones cruzadas (WP <-> Bricks <-> Theme)
- `e2e/`: escenarios de extremo a extremo

## Convenciones de archivos
- `[feature]-[tipo]-[id].md`
- Ejemplo: `custom-fields-manual-001.md`

## Archivos de Testing Disponibles

### Manual Tests

#### Security & Optimization
- `security-optimization-manual-001.md` - Configuraciones básicas de seguridad
- `security-optimization-manual-002.md` - Limpieza avanzada del head

#### Head Optimization
- `head-optimization-manual-001.md` - Optimizaciones automáticas del head

#### WebP Image Optimization
- `webp-optimization-manual-001.md` - Sistema completo de optimización WebP

### Integration Tests

#### Core Web Vitals
- `core-web-vitals-integration-001.md` - Verificación completa de Core Web Vitals

## Cómo Usar Estos Tests

1. **Seleccionar el test apropiado** según la funcionalidad a probar
2. **Completar la información** en los campos marcados con `[FECHA]`, `[NOMBRE]`, etc.
3. **Seguir los pasos** en orden
4. **Marcar las casillas** de verificación conforme se completen
5. **Capturar evidencia** (screenshots, logs, métricas)
6. **Documentar problemas** en la sección de notas
7. **Actualizar el estado** del test al finalizar

## Estado de los Tests

- ⏳ **Pendiente**: Test no iniciado
- 🔄 **En Progreso**: Test en ejecución
- ✅ **Completado**: Test finalizado exitosamente
- ❌ **Fallido**: Test completado con fallos
- ⚠️ **Advertencias**: Test completado con advertencias

## Próximos Tests a Crear

- [ ] Assets Optimization - Tests manuales
- [ ] XML Sitemaps - Tests manuales
- [ ] Image Auto Optimizer - Tests manuales
- [ ] Role Manager - Tests manuales
- [ ] Custom Elements (Bricks) - Tests de integración
- [ ] E2E Tests - Escenarios completos

## Herramientas Recomendadas

### Para Medir Core Web Vitals
- **Lighthouse** (Chrome DevTools)
- **PageSpeed Insights** (Google)
- **WebPageTest**
- **Chrome User Experience Report**

### Para Inspección
- **Chrome DevTools**
- **Firefox Developer Tools**
- **Network Tab** (para verificar requests)
- **Elements Tab** (para verificar HTML)

### Para Testing
- **Postman** (para APIs)
- **curl** (para peticiones HTTP)
- **WPScan** (para seguridad)

## Notas Importantes

- Los tests están diseñados para ser ejecutados en orden
- Algunos tests requieren configuración previa
- Siempre documentar problemas encontrados
- Comparar métricas antes/después cuando sea posible
- Mantener evidencia de todas las pruebas