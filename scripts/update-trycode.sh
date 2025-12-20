#!/bin/bash

# Script para crear/actualizar la rama de pruebas trycode como orphan (sin historial)
# Uso: ./scripts/update-trycode.sh
# Crea trycode basándose en lo último de prd
# -- Script Re-generado --

set -e

TRYCODE_BRANCH="trycode"
SOURCE_BRANCH="prd"
CURRENT_BRANCH=$(git branch --show-current)

echo "🔄 Actualizando rama de pruebas: $TRYCODE_BRANCH"
echo "📦 Fuente: $SOURCE_BRANCH"
echo ""

# Verificar que la rama prd existe
if ! git show-ref --verify --quiet refs/heads/$SOURCE_BRANCH; then
    echo "❌ Error: La rama $SOURCE_BRANCH no existe localmente. Ejecuta primero create-release.sh"
    exit 1
fi

# Asegurarse de estar en prd
if [ "$CURRENT_BRANCH" != "$SOURCE_BRANCH" ]; then
    echo "📍 Cambiando a rama $SOURCE_BRANCH..."
    git checkout $SOURCE_BRANCH
fi

# Intentar pull si hay remoto
if git remote -v | grep -q origin; then
    echo "📥 Actualizando $SOURCE_BRANCH desde remoto..."
    git pull origin $SOURCE_BRANCH || echo "⚠️ No se pudo hacer pull"
fi

# Verificar si la rama trycode existe
if git show-ref --verify --quiet refs/heads/$TRYCODE_BRANCH; then
    echo "🔄 La rama $TRYCODE_BRANCH ya existe, reseteando completamente..."
    git checkout $TRYCODE_BRANCH
    
    echo "🧹 Limpiando archivos actuales..."
    git rm -rf --cached . 2>/dev/null || true
    git clean -fd
    
    echo "🗑️  Eliminando historial de commits..."
    git reset --hard
    
    echo "📋 Copiando archivos frescos desde $SOURCE_BRANCH..."
    git checkout $SOURCE_BRANCH -- .
    
    echo "📝 Creando commit de actualización..."
    git add .
    git commit --allow-empty -m "Actualizar trycode desde $SOURCE_BRANCH - $(date +'%Y-%m-%d %H:%M:%S')"
    
else
    echo "🆕 La rama $TRYCODE_BRANCH no existe, creando rama orphan..."
    git checkout --orphan $TRYCODE_BRANCH
    
    echo "🧹 Limpiando área de staging..."
    git rm -rf --cached . 2>/dev/null || true
    
    echo "📋 Copiando archivos desde $SOURCE_BRANCH..."
    git checkout $SOURCE_BRANCH -- .
    
    echo "📝 Creando commit inicial limpio..."
    git add .
    git commit -m "Rama trycode inicial desde $SOURCE_BRANCH - $(date +'%Y-%m-%d %H:%M:%S')"
fi

# Subir si hay remoto
if git remote -v | grep -q origin; then
    echo "📤 Subiendo cambios a remoto..."
    git push -f origin $TRYCODE_BRANCH
fi

echo ""
echo "✅ ¡Rama trycode actualizada!"
echo "🔄 Volviendo a develop..."
git checkout develop
