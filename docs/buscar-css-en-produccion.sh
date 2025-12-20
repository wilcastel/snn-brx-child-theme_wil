#!/bin/bash
# Script para buscar referencias al CSS problemático en producción

echo "=== Buscando referencias al CSS A.style.min.css ==="
echo ""

# Buscar en plugins
echo "1. Buscando en plugins..."
grep -r "A.style.min.css" ../plugins/ 2>/dev/null
grep -r "wp-block-library" ../plugins/ 2>/dev/null | grep -i "enqueue\|register\|wp_enqueue"

# Buscar en el tema (si hay otros temas)
echo ""
echo "2. Buscando en otros temas..."
grep -r "A.style.min.css" ../themes/ 2>/dev/null
grep -r "wp-block-library" ../themes/ 2>/dev/null | grep -i "enqueue\|register\|wp_enqueue"

# Buscar en WordPress core (menos probable)
echo ""
echo "3. Verificando si WordPress core lo está cargando..."
grep -r "A.style" ../../wp-includes/ 2>/dev/null | head -5

# Buscar en wp-config o mu-plugins
echo ""
echo "4. Buscando en mu-plugins..."
if [ -d "../mu-plugins" ]; then
    grep -r "wp-block-library\|A.style" ../mu-plugins/ 2>/dev/null
fi

echo ""
echo "=== Búsqueda completada ==="
echo ""
echo "Para buscar específicamente qué está haciendo enqueue:"
echo "grep -r 'wp_enqueue_style.*block' ../plugins/ ../themes/"




