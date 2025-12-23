# Script: send-to-trycode.sh

## 📋 Descripción

Script para enviar cambios pendientes a la rama `trycode` desde cualquier rama de trabajo, permitiendo probar cambios antes de hacer commit definitivo.

## 🎯 Uso

### Uso básico:
```bash
./scripts/send-to-trycode.sh
```

### Con mensaje personalizado:
```bash
./scripts/send-to-trycode.sh "fix: Corregir problema de Redis"
```

## 🔄 Cómo Funciona

1. **Detecta la rama actual** donde estás trabajando
2. **Guarda los cambios** pendientes en stash
3. **Cambia a trycode** (o la crea si no existe)
4. **Actualiza trycode** con el código de tu rama actual (reset hard)
5. **Aplica los cambios** guardados
6. **Hace commit** en trycode con el mensaje especificado
7. **Vuelve a tu rama original** y restaura los cambios

## ✅ Ventajas

- ✅ No afecta tu rama de trabajo actual
- ✅ trycode siempre tiene el código más reciente de tu rama
- ✅ Permite probar cambios antes de commitear
- ✅ Fácil de usar desde cualquier rama

## 📝 Ejemplos

### Ejemplo 1: Enviar cambios con mensaje por defecto
```bash
# Estás en rama "feature-redis-optimization"
./scripts/send-to-trycode.sh
# Crea commit: "wip: Cambios para probar en trycode desde feature-redis-optimization"
```

### Ejemplo 2: Enviar cambios con mensaje personalizado
```bash
./scripts/send-to-trycode.sh "feat: Mejorar detección de Redis"
```

### Ejemplo 3: Flujo completo de trabajo
```bash
# 1. Trabajas en tu rama
git checkout -b mi-feature
# ... haces cambios ...

# 2. Envías a trycode para probar
./scripts/send-to-trycode.sh "wip: Probar nueva funcionalidad"

# 3. Pruebas en servidor de desarrollo
git push origin trycode

# 4. Si funciona, haces commit en tu rama
git add -A
git commit -m "feat: Nueva funcionalidad"

# 5. Si no funciona, sigues trabajando en tu rama
# Los cambios ya están de vuelta en tu rama
```

## ⚠️ Notas Importantes

1. **trycode se actualiza completamente** con el código de tu rama actual
2. **Los cambios pendientes** se envían como un nuevo commit en trycode
3. **Tu rama original** no se modifica
4. **Si hay conflictos** al aplicar cambios, el script te avisará

## 🚨 Solución de Problemas

### Error: "No hay cambios para enviar"
- Verifica que tengas cambios sin commitear: `git status`

### Error: "Error al aplicar cambios"
- Puede haber conflictos. Revisa: `git stash list`
- Resuelve manualmente: `git checkout trycode` y `git stash pop`

### trycode tiene código antiguo
- Esto es normal, el script actualiza trycode con tu rama actual
- Si quieres mantener código específico en trycode, hazlo manualmente

## 🔗 Ver También

- `scripts/update-trycode.sh` - Actualizar trycode desde develop
- `docs/redis-server-configuration.md` - Configuración del servidor

