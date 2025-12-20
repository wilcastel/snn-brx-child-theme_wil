# Arquitectura y Módulos

## Visión General
El tema sigue un diseño modular. El archivo `functions.php` define constantes (`SNN_PATH`, `SNN_URL`) y registra los módulos dentro de `includes/`, además de registrar elementos de Bricks condicionalmente.

## Directorios Clave
- `includes/` módulos funcionales y páginas de ajustes
  - `[feature]-settings.php`: páginas de administración con Settings API
  - `elements/`: clases `\Bricks\Element` por elemento
  - `ai/`: UI/ajustes/API para IA
  - `dynamic-data-tags/`: etiquetas de datos dinámicos
  - `query/`: utilidades de consultas
- `assets/`: CSS/JS e imágenes
- `languages/`: i18n con dominio `snn`

## Patrones
- Prefijo funciones: `snn_`
- Opciones: `snn_[feature]_settings` u opciones específicas
- Hooks: `admin_menu`, `admin_init`, `wp_enqueue_scripts`, `init`
- Elementos Bricks: clase con `category 'snn'`, `set_controls()`, `render()`

## Carga Condicional
- Elementos GSAP/Lottie se registran si `enqueue_gsap` está activo en `snn_other_settings`.

## Seguridad
- Nonces, `current_user_can('manage_options')`, sanitización robusta (e.g., `sanitize_text_field`, `sanitize_key`).

## Decisiones Actuales
- Mantener modularidad y prefijos.
- Documentar cualquier nuevo módulo aquí con: objetivo, entradas, salidas, hooks, opciones y dependencias.
