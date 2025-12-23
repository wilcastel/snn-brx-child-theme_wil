#!/bin/bash

# Script para sincronizar trycode en el servidor
# Uso: ./scripts/sync-trycode-server.sh
# 
# Este script actualiza trycode con el código del remoto
# Usa reset --hard porque trycode es una rama de pruebas que se actualiza completamente

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

info "Rama actual: ${CURRENT_BRANCH}"

# Verificar si estamos en trycode
if [ "$CURRENT_BRANCH" != "trycode" ]; then
    warning "No estás en la rama trycode. Cambiando a trycode..."
    git checkout trycode
fi

# Verificar si hay cambios sin commitear
if ! git diff-index --quiet HEAD --; then
    warning "Hay cambios sin commitear en trycode."
    read -p "¿Deseas descartarlos y sincronizar con el remoto? (s/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Ss]$ ]]; then
        info "Operación cancelada."
        exit 0
    fi
    info "Descartando cambios locales..."
    git reset --hard HEAD
fi

# Hacer fetch del remoto
info "Obteniendo cambios del remoto..."
git fetch origin trycode

# Resetear trycode al remoto (force)
info "Sincronizando trycode con origin/trycode..."
git reset --hard origin/trycode

success "✅ trycode sincronizado exitosamente con el remoto"

# Mostrar información
COMMIT_HASH=$(git rev-parse --short HEAD)
COMMIT_MSG=$(git log -1 --pretty=format:"%s")
info "Último commit: ${COMMIT_HASH} - ${COMMIT_MSG}"

echo ""
success "¡Listo! trycode está actualizado con el código del remoto."

