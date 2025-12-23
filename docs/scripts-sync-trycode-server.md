# Script: sync-trycode-server.sh

## 📋 Descripción

Script para sincronizar la rama `trycode` en el servidor con el remoto. Útil cuando se hace force push desde desarrollo y necesitas actualizar el servidor.

## 🎯 Uso

```bash
# En el servidor, dentro del directorio del tema
./scripts/sync-trycode-server.sh
```

## 🔄 Cómo Funciona

1. **Verifica** que estás en un repositorio git
2. **Cambia** a la rama trycode si no estás en ella
3. **Advierte** si hay cambios sin commitear (pregunta antes de descartarlos)
4. **Hace fetch** del remoto
5. **Resetea** trycode completamente al remoto (reset --hard)

## ⚠️ Importante

- **Este script descarta cambios locales** en trycode
- Es seguro porque trycode es una rama de pruebas
- Siempre pregunta antes de descartar cambios

## 📝 Ejemplo de Uso

```bash
# En el servidor
cd /home/lanacionweb/htdocs/lanacionweb.com/wp-content/themes/snn-brx-child-theme_wil
./scripts/sync-trycode-server.sh
```

## 🔗 Relacionado

- `scripts/send-to-trycode.sh` - Script para enviar cambios a trycode desde desarrollo
- `scripts/update-trycode.sh` - Script para actualizar trycode desde prd

