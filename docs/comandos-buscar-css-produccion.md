# Comandos para ejecutar en SSH del servidor de producción

## Ubicación inicial
Primero, ve a la carpeta del WordPress:
```bash
cd /ruta/a/tu/sitio/wp-content
```

O si no sabes la ruta exacta, busca así:
```bash
find /home -name "wp-content" -type d 2>/dev/null | head -5
```

## Comandos para encontrar el origen

### 1. Buscar en todos los plugins que puedan estar haciendo enqueue del block library
```bash
cd wp-content/plugins
grep -r "wp-block-library" . --include="*.php" | grep -E "enqueue|register|wp_enqueue_style"
```

### 2. Buscar cualquier referencia a block-library en plugins
```bash
cd wp-content/plugins
grep -r "block-library" . --include="*.php"
```

### 3. Buscar si algún plugin está registrando o enqueueando estilos relacionados con blocks
```bash
cd wp-content/plugins
grep -r "wp_enqueue_style\|wp_register_style" . --include="*.php" | grep -i "block"
```

### 4. Buscar en el tema activo (tu tema hijo)
```bash
cd wp-content/themes/snn-brx-child-theme
grep -r "wp-block-library\|block-library" . --include="*.php" | grep -E "enqueue|register|wp_enqueue"
```

### 5. Buscar en mu-plugins (plugins must-use)
```bash
cd wp-content
if [ -d "mu-plugins" ]; then
    cd mu-plugins
    grep -r "wp-block-library\|block-library" . --include="*.php" 2>/dev/null
fi
```

### 6. Buscar si hay algún plugin de caché/optimización que esté modificando URLs
```bash
cd wp-content/plugins
grep -r "pagespeed\|cloudflare\|minify" . --include="*.php" | grep -i "style\|css"
```

### 7. Buscar referencias directas al archivo A.style.min.css (aunque es poco probable)
```bash
cd wp-content
grep -r "A.style.min.css" plugins/ themes/ mu-plugins/ 2>/dev/null
```

### 8. Ver todos los archivos que están haciendo enqueue de estilos (más amplio)
```bash
cd wp-content
grep -r "wp_enqueue_style" plugins/ themes/ mu-plugins/ --include="*.php" | grep -i "block"
```

### 9. Buscar específicamente en archivos de configuración o inicialización
```bash
cd wp-content/plugins
find . -name "*init*.php" -o -name "*main*.php" -o -name "*plugin*.php" | xargs grep -l "wp-block-library\|block-library" 2>/dev/null
```

### 10. Ver qué plugins tienen archivos relacionados con blocks
```bash
cd wp-content/plugins
find . -name "*.php" -exec grep -l "wp-block-library\|block-library" {} \; 2>/dev/null
```

## Comando TODO-EN-UNO (ejecuta este primero para ver todo)

```bash
cd wp-content && echo "=== BUSCANDO EN PLUGINS ===" && grep -r "wp-block-library\|block-library" plugins/ --include="*.php" | grep -E "enqueue|register|wp_enqueue" && echo "" && echo "=== BUSCANDO EN EL TEMA ===" && grep -r "wp-block-library\|block-library" themes/snn-brx-child-theme/ --include="*.php" | grep -E "enqueue|register|wp_enqueue" && echo "" && echo "=== BUSCANDO PLUGINS QUE MODIFICAN CSS ===" && grep -r "pagespeed\|cloudflare" plugins/ --include="*.php" | grep -i "css\|style" | head -10
```

## Cómo interpretar los resultados

Cuando ejecutes los comandos, busca líneas que contengan:
- `wp_enqueue_style('wp-block-library'`
- `wp_register_style('wp-block-library'`
- `wp_enqueue_style( 'wp-block-library'`
- `enqueue_block_editor_assets` (puede cargar CSS relacionado)

**El archivo que contenga una de estas líneas es el culpable.**

## Verificar si WordPress core lo está cargando automáticamente

Para verificar si WordPress está cargando esto por defecto:
```bash
cd /ruta/a/wordpress/wp-includes
grep -r "wp-block-library\|block-library" . --include="*.php" | head -10
```

## Si encuentras el archivo culpable

Una vez que identifiques el archivo, puedes:
1. Ver el contexto alrededor de esa línea:
```bash
grep -n -A 5 -B 5 "wp-block-library" /ruta/completa/al/archivo.php
```

2. Ver todo el archivo para entender qué hace:
```bash
cat /ruta/completa/al/archivo.php | less
```




