#!/bin/bash

# Script para desplegar cambios desde la rama actual directamente a trycode
# Uso: ./scripts/deploy-current-to-trycode.sh
# Mantiene la rama actual y actualiza trycode con sus cambios
# -- Script para despliegue rápido a pruebas --

set -e

TRYCODE_BRANCH="trycode"
CURRENT_BRANCH=$(git branch --show-current)

# Verificar que hay cambios o que la rama actual existe
if [ -z "$CURRENT_BRANCH" ]; then
    echo "❌ Error: No se pudo detectar la rama actual"
    exit 1
fi

echo "🚀 Desplegando cambios desde rama actual a trycode"
echo "📍 Rama actual: $CURRENT_BRANCH"
echo "🎯 Destino: $TRYCODE_BRANCH"
echo ""

# Verificar que hay cambios guardados (no hay cambios sin commit)
if ! git diff-index --quiet HEAD --; then
    echo "⚠️  Advertencia: Tienes cambios sin guardar en la rama actual"
    echo "💡 Los cambios sin commit NO se incluirán en el despliegue"
    read -p "¿Continuar de todos modos? (s/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Ss]$ ]]; then
        echo "❌ Operación cancelada"
        exit 1
    fi
fi

# Verificar si la rama trycode existe
if git show-ref --verify --quiet refs/heads/$TRYCODE_BRANCH; then
    echo "🔄 La rama $TRYCODE_BRANCH ya existe, actualizando..."
    
    # Guardar la rama actual
    ORIGINAL_BRANCH=$CURRENT_BRANCH
    
    # Cambiar a trycode
    git checkout $TRYCODE_BRANCH
    
    # Limpiar archivos actuales
    echo "🧹 Limpiando archivos actuales en trycode..."
    git rm -rf --cached . 2>/dev/null || true
    git clean -fd
    
    # Copiar archivos desde la rama original
    echo "📋 Copiando archivos desde $ORIGINAL_BRANCH..."
    git checkout $ORIGINAL_BRANCH -- .
    
    # Crear commit de actualización
    echo "📝 Creando commit de actualización..."
    git add .
    git commit --allow-empty -m "Deploy desde $ORIGINAL_BRANCH a trycode - $(date +'%Y-%m-%d %H:%M:%S')" || {
        echo "⚠️  No hay cambios para commitear (los archivos ya están actualizados)"
    }
    
    # Subir si hay remoto
    if git remote -v | grep -q origin; then
        echo "📤 Subiendo cambios a remoto..."
        git push -f origin $TRYCODE_BRANCH
    fi
    
    # Volver a la rama original
    echo "🔄 Volviendo a rama original: $ORIGINAL_BRANCH"
    git checkout $ORIGINAL_BRANCH
    
else
    echo "🆕 La rama $TRYCODE_BRANCH no existe, creando rama orphan..."
    
    # Guardar la rama actual
    ORIGINAL_BRANCH=$CURRENT_BRANCH
    
    # Crear rama orphan
    git checkout --orphan $TRYCODE_BRANCH
    
    # Limpiar área de staging
    echo "🧹 Limpiando área de staging..."
    git rm -rf --cached . 2>/dev/null || true
    
    # Copiar archivos desde la rama original
    echo "📋 Copiando archivos desde $ORIGINAL_BRANCH..."
    git checkout $ORIGINAL_BRANCH -- .
    
    # Crear commit inicial
    echo "📝 Creando commit inicial..."
    git add .
    git commit -m "Rama trycode inicial desde $ORIGINAL_BRANCH - $(date +'%Y-%m-%d %H:%M:%S')"
    
    # Subir si hay remoto
    if git remote -v | grep -q origin; then
        echo "📤 Subiendo rama a remoto..."
        git push -f origin $TRYCODE_BRANCH
    fi
    
    # Volver a la rama original
    echo "🔄 Volviendo a rama original: $ORIGINAL_BRANCH"
    git checkout $ORIGINAL_BRANCH
fi

echo ""
echo "✅ ¡Cambios desplegados exitosamente a trycode!"
echo "📍 Estás de vuelta en: $CURRENT_BRANCH"
