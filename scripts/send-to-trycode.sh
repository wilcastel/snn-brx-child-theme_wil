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

# Guardar cambios actuales en stash
info "Guardando cambios actuales..."
git stash push -m "Temporal para send-to-trycode - $(date +%Y%m%d_%H%M%S)"

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

# Aplicar los cambios guardados
info "Aplicando cambios..."
if ! git stash pop; then
    error "Error al aplicar cambios. Puede haber conflictos."
    warning "Los cambios están guardados en stash. Usa 'git stash list' para verlos."
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

# Volver a la rama original
info "Volviendo a rama ${CURRENT_BRANCH}..."
git checkout "${CURRENT_BRANCH}"

# Aplicar cambios de vuelta (si quedaron en stash)
if git stash list | grep -q "send-to-trycode"; then
    info "Aplicando cambios de vuelta a ${CURRENT_BRANCH}..."
    git stash pop
fi

success "¡Listo! Cambios enviados a trycode"
echo ""
info "Próximos pasos:"
echo "  1. Revisa el commit en trycode: git show trycode"
echo "  2. Si quieres hacer push: git push origin trycode"
echo "  3. Si quieres probar localmente: git checkout trycode"
echo ""
info "Para volver a tu rama: git checkout ${CURRENT_BRANCH}"

