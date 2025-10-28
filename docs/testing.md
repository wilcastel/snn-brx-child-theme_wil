# Guía de Pruebas

## Objetivo
Estandarizar la verificación de calidad para nuevas funcionalidades del tema.

## Tipos de Pruebas
- Manual: flujos del panel `SNN Settings`, creación de CPTs, taxonomías, campos y validación de guardado.
- Integración: comportamiento de elementos Bricks en frontend y en builder.
- E2E (opcional): escenarios críticos (login, 301 redirects, formularios, AI overlay básico).

## Estructura
- `tests/manual/`: checklists y casos manuales por funcionalidad
- `tests/integration/`: scripts o notas de validación cruzada
- `tests/e2e/`: escenarios completos (cuando se configure una herramienta)

## Convenciones
- Nombrar archivos como `[feature]-[tipo]-[id].md`
- Incluir: Precondiciones, Pasos, Resultado esperado, Evidencia (capturas/URL)

## Herramientas sugeridas
- Navegadores con perfiles limpios
- Extensiones para logs de red
- Para E2E futuro: Playwright o Cypress
