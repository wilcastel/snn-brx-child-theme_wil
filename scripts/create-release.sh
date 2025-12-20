#!/bin/bash

# Script para crear/actualizar rama de producción (prd) sin historial
# Uso: ./scripts/create-release.sh v0.0.1
# La rama fija es: prd
# Los tags son: v0.0.1, v0.0.2, etc.

set -e

VERSION=$1

if [ -z "$VERSION" ]; then
    echo "❌ Error: Debes proporcionar una versión"
    echo "Uso: ./scripts/create-release.sh v0.0.1"
    exit 1
fi

# Validar formato de versión
if [[ ! $VERSION =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "❌ Error: Formato de versión inválido. Debe ser: v0.0.1"
    exit 1
fi

RELEASE_BRANCH="prd"
CURRENT_BRANCH=$(git branch --show-current)

echo "🚀 Creando/actualizando rama de producción: $RELEASE_BRANCH"
echo "📦 Versión: $VERSION"
echo "📍 Rama actual: $CURRENT_BRANCH"
echo ""

# Asegurarse de estar en develop y actualizado
if [ "$CURRENT_BRANCH" != "develop" ]; then
    echo "⚠️  Cambiando a rama develop..."
    git checkout develop
fi

# Intentar pull solo si hay remoto configurado
if git remote -v | grep -q origin; then
    echo "📥 Actualizando develop desde remoto..."
    git pull origin develop || echo "⚠️ No se pudo hacer pull (quizás es el primer push)"
fi

# Verificar si la rama prd ya existe
if git show-ref --verify --quiet refs/heads/$RELEASE_BRANCH; then
    echo "ℹ️  La rama $RELEASE_BRANCH ya existe, se actualizará"
    git checkout $RELEASE_BRANCH
    
    echo "🧹 Limpiando archivos actuales..."
    git rm -rf --cached . 2>/dev/null || true
    git clean -fd
    
    echo "📋 Copiando archivos actualizados desde develop..."
    git checkout develop -- .
    
    echo "📝 Creando commit de actualización..."
    git add .
    git commit -m "Release $VERSION - SNN Child Theme"
else
    echo "🆕 Creando rama orphan (sin historial)..."
    git checkout --orphan $RELEASE_BRANCH
    
    echo "🧹 Limpiando área de staging..."
    git rm -rf --cached . 2>/dev/null || true
    
    echo "📋 Copiando archivos desde develop..."
    git checkout develop -- .
    
    echo "📝 Creando commit inicial..."
    git add .
    git commit -m "Release $VERSION - SNN Child Theme"
fi

echo "🏷️  Creando tag $VERSION..."
git tag -a $VERSION -m "Release $VERSION - Producción"

# Subir si hay remoto
if git remote -v | grep -q origin; then
    echo "📤 Subiendo rama y tag a remoto..."
    git push -f origin $RELEASE_BRANCH
    git push origin $VERSION
else
    echo "⚠️ No hay remoto configurado. Recuerda hacer push cuando configures el origen."
fi

echo ""
echo "✅ ¡Rama de producción ($RELEASE_BRANCH) creada/actualizada exitosamente!"
echo "🔄 Volviendo a develop..."
git checkout develop

