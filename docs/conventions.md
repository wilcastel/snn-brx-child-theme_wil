# Convenciones y Estándares

## Nomenclatura
- Funciones: `snn_[feature]_[acción]`
- Opciones: `snn_[feature]_settings` o `snn_[feature]`
- Nonces: `snn_[feature]_nonce`
- Slugs de páginas: `snn-[feature]`
- Clases Bricks: `[Prefix]_Element_[Nombre]`

## Hooks
- Admin: `admin_menu` para submenús, `admin_init` para Settings API
- Front: `wp_enqueue_scripts`, `wp_footer` condicional para herramientas de builder

## Internacionalización
- Dominio: `snn`
- Usar `__(...)`, `esc_html__`, `esc_attr__`, `esc_html_e`

## Seguridad
- Verificar `current_user_can('manage_options')` en admin
- Validar Nonce antes de procesar POST
- Sanitizar todo input (`sanitize_text_field`, `sanitize_key`, `wp_kses_post`, etc.)

## Estilos/Assets
- Carga condicional y versionado con `filemtime`
- `wp_localize_script` para pasar `ajaxUrl` y `nonce`

## Documentación
- Cada nueva funcionalidad debe añadir sección en `docs/architecture.md` y, si aplica, guía breve en `docs/testing.md`.
