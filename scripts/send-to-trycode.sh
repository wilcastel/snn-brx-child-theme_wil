#!/bin/bash

# Script para enviar cambios a trycode desde cualquier rama
# Uso: ./scripts/send-to-trycode.sh [mensaje de commit]

set -e  # Salir si hay errores

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para mostrar mensajes
info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

success() {
    echo -e "${GREEN}✅${NC} $1"
}

warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

error() {
    echo -e "${RED}❌${NC} $1"
}

# Verificar que estamos en un repositorio git
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    error "No estás en un repositorio git"
    exit 1
fi

# Obtener la rama actual
CURRENT_BRANCH=$(git branch --show-current)

if [ -z "$CURRENT_BRANCH" ]; then
    error "No se pudo determinar la rama actual"
    exit 1
fi

info "Rama actual: ${CURRENT_BRANCH}"

# Verificar si hay cambios sin commitear
if git diff-index --quiet HEAD --; then
    error "No hay cambios para enviar a trycode"
    exit 1
fi

# Verificar si hay cambios en staging
HAS_STAGED=$(git diff --cached --quiet; echo $?)
HAS_UNSTAGED=$(git diff --quiet; echo $?)

if [ "$HAS_STAGED" -eq 0 ] && [ "$HAS_UNSTAGED" -eq 0 ]; then
    error "No hay cambios para enviar a trycode"
    exit 1
fi

# Mensaje de commit (opcional)
COMMIT_MSG="${1:-wip: Cambios para probar en trycode desde ${CURRENT_BRANCH}}"

info "Mensaje de commit: ${COMMIT_MSG}"

# Guardar cambios actuales en stash con un identificador único
STASH_MESSAGE="send-to-trycode-${CURRENT_BRANCH}-$(date +%Y%m%d_%H%M%S)"
info "Guardando cambios actuales en stash: ${STASH_MESSAGE}..."
git stash push -m "${STASH_MESSAGE}"
STASH_INDEX=$(git stash list | grep -m1 "${STASH_MESSAGE}" | cut -d: -f1 | sed 's/[^0-9]//g')

# Verificar si trycode existe localmente
if git show-ref --verify --quiet refs/heads/trycode; then
    info "Rama trycode existe localmente"
    TRYCODE_EXISTS=true
else
    warning "Rama trycode no existe localmente, se creará"
    TRYCODE_EXISTS=false
fi

# Cambiar a trycode o crearla desde la rama actual
if [ "$TRYCODE_EXISTS" = true ]; then
    info "Cambiando a rama trycode..."
    git checkout trycode
    
    # Actualizar trycode con el código de la rama actual
    info "Actualizando trycode con código de ${CURRENT_BRANCH}..."
    git reset --hard "${CURRENT_BRANCH}"
else
    info "Creando rama trycode desde ${CURRENT_BRANCH}..."
    git checkout -b trycode "${CURRENT_BRANCH}"
fi

# Aplicar los cambios guardados (sin eliminar del stash - usamos apply, no pop)
info "Aplicando cambios en trycode..."
# Buscar el stash más reciente con nuestro mensaje
LATEST_STASH=$(git stash list | grep -m1 "send-to-trycode-${CURRENT_BRANCH}" | cut -d: -f1)
if [ -n "$LATEST_STASH" ]; then
    if ! git stash apply "${LATEST_STASH}"; then
        error "Error al aplicar cambios. Puede haber conflictos."
        warning "Los cambios están guardados en stash. Usa 'git stash list' para verlos."
        git checkout "${CURRENT_BRANCH}"
        exit 1
    fi
    # Guardar referencia al stash para restaurarlo después
    STASH_TO_RESTORE="${LATEST_STASH}"
else
    error "No se encontró el stash guardado."
    git checkout "${CURRENT_BRANCH}"
    exit 1
fi

# Agregar todos los cambios
info "Agregando cambios al staging..."
git add -A

# Hacer commit
info "Haciendo commit en trycode..."
if git commit -m "${COMMIT_MSG}"; then
    success "Commit creado exitosamente en trycode"
else
    error "Error al crear commit"
    git checkout "${CURRENT_BRANCH}"
    exit 1
fi

# Mostrar información del commit
COMMIT_HASH=$(git rev-parse --short HEAD)
info "Commit creado: ${COMMIT_HASH}"

# Hacer push a trycode si hay remoto configurado
if git remote -v | grep -q origin; then
    info "Haciendo push a trycode en remoto..."
    if git push origin trycode; then
        success "Push exitoso a origin/trycode"
    else
        warning "No se pudo hacer push. Puedes hacerlo manualmente con: git push origin trycode"
    fi
else
    warning "No hay remoto configurado. No se puede hacer push automático."
fi

# Volver a la rama original
info "Volviendo a rama ${CURRENT_BRANCH}..."
git checkout "${CURRENT_BRANCH}"

# Restaurar cambios de vuelta desde stash
info "Restaurando cambios en ${CURRENT_BRANCH}..."
if [ -n "$STASH_TO_RESTORE" ]; then
    # Usar el stash que guardamos antes
    if git stash apply "${STASH_TO_RESTORE}"; then
        success "Cambios restaurados exitosamente en ${CURRENT_BRANCH}"
        info "El stash se mantiene guardado. Puedes eliminarlo manualmente si quieres:"
        info "  git stash drop ${STASH_TO_RESTORE}"
    else
        warning "Hubo conflictos al restaurar los cambios."
        warning "El stash sigue disponible: ${STASH_TO_RESTORE}"
        info "Puedes restaurar manualmente con: git stash apply ${STASH_TO_RESTORE}"
        info "O ver el contenido con: git stash show -p ${STASH_TO_RESTORE}"
    fi
else
    # Fallback: buscar el stash más reciente con nuestro mensaje
    RESTORE_STASH=$(git stash list | grep -m1 "send-to-trycode-${CURRENT_BRANCH}" | cut -d: -f1)
    if [ -n "$RESTORE_STASH" ]; then
        info "Aplicando stash encontrado: ${RESTORE_STASH}"
        if git stash apply "${RESTORE_STASH}"; then
            success "Cambios restaurados exitosamente"
        else
            warning "Hubo conflictos al restaurar. Revisa manualmente con: git stash list"
        fi
    else
        warning "No se encontró el stash para restaurar."
        info "Los cambios pueden estar solo en trycode. Verifica con:"
        info "  git checkout trycode"
        info "  git diff ${CURRENT_BRANCH}"
        info ""
        info "O ver todos los stashes: git stash list"
    fi
fi

success "¡Listo! Cambios enviados a trycode"
echo ""
info "Resumen:"
echo "  - Rama actual: ${CURRENT_BRANCH}"
echo "  - Cambios enviados a: trycode (commit ${COMMIT_HASH})"
if [ -n "$STASH_TO_RESTORE" ]; then
    echo "  - Cambios restaurados en: ${CURRENT_BRANCH}"
    echo "  - Stash guardado: ${STASH_TO_RESTORE}"
else
    echo "  - ⚠️  Verifica que los cambios se restauraron correctamente"
fi
echo ""
info "Próximos pasos:"
echo "  1. Revisa el commit en trycode: git show trycode"
if git remote -v | grep -q origin; then
    echo "  2. ✅ Push realizado automáticamente a origin/trycode"
else
    echo "  2. ⚠️  No hay remoto configurado. Haz push manualmente: git push origin trycode"
fi
echo "  3. Prueba en el servidor de desarrollo"
echo "  4. Si funciona, haz commit en ${CURRENT_BRANCH}: git add -A && git commit -m '...'"
if [ -n "$STASH_TO_RESTORE" ]; then
    echo ""
    info "Para limpiar el stash (opcional): git stash drop ${STASH_TO_RESTORE}"
fi

