# Script: send-to-trycode.sh

## 📋 Descripción

Script simple para enviar cambios pendientes a la rama `trycode` desde cualquier rama de trabajo. Permite probar cambios en el servidor antes de hacer commit definitivo.

## 🎯 Uso

```bash
# Desde tu rama de trabajo, con cambios sin commitear
./scripts/send-to-trycode.sh "mensaje del commit"
```

**Ejemplo:**
```bash
./scripts/send-to-trycode.sh "fix: Corregir problema de Redis"
```

## 🔄 Cómo Funciona

1. Guarda tus cambios pendientes
2. Va a trycode y la actualiza con tu rama
3. Aplica tus cambios y hace commit
4. Hace push automático a origin/trycode
5. Vuelve a tu rama y restaura tus cambios

## ✅ Ventajas

- ✅ No afecta tu rama de trabajo actual
- ✅ trycode siempre tiene el código más reciente de tu rama
- ✅ Permite probar cambios antes de commitear
- ✅ Fácil de usar desde cualquier rama

## 📝 Flujo Completo

```bash
# 1. Trabajas en tu rama (ej: optimizacion_cloudpanel)
# ... haces cambios en archivos ...

# 2. Envías a trycode para probar
./scripts/send-to-trycode.sh "wip: Probar mejoras de Redis"
# ✅ Push automático realizado

# 3. En el servidor de pruebas:
cd /ruta/al/tema
git checkout trycode
git pull origin trycode

# 4. Pruebas en el servidor

# 5. Si funciona, haces commit en tu rama:
git add -A
git commit -m "feat: Mejoras de Redis"

# 6. Si no funciona, ajustas y repites desde paso 2
# Los cambios siguen en tu rama sin commitear
```

## ⚠️ Notas Importantes

- **trycode se actualiza completamente** con el código de tu rama actual
- **Los cambios se restauran** automáticamente en tu rama original
- **Push es automático** - no necesitas hacer nada más
- **En el servidor**, simplemente haz `git pull origin trycode`

## 🚨 Solución de Problemas

### Error: "No hay cambios para enviar"
Verifica que tengas cambios sin commitear: `git status`

### Error: "Error al aplicar cambios"
Los cambios están guardados en stash. Revisa: `git stash list`

### En el servidor: "divergent branches"
```bash
git fetch origin trycode
git reset --hard origin/trycode
```

