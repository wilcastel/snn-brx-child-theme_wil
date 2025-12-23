# Comandos para el Servidor - trycode

## 📋 Comandos Simples

Cuando necesites traer cambios de trycode en el servidor:

```bash
cd /home/lanacionweb/htdocs/lanacionweb.com/wp-content/themes/snn-brx-child-theme_wil
git fetch origin trycode
git checkout trycode
git reset --hard origin/trycode
```

## ⚠️ Si sale error "divergent branches"

Simplemente usa `reset --hard`:

```bash
git fetch origin trycode
git reset --hard origin/trycode
```

Esto es seguro porque trycode es una rama de pruebas que se actualiza completamente.

