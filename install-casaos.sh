#!/bin/bash
# =============================================================
# Inventario Taller — Script de preparación para CasaOS
# Ejecutar UNA VEZ antes de importar el docker-compose.yml
# Uso:  bash install-casaos.sh
# =============================================================
set -e

BASE=/DATA/AppData/inventaller
REPO="$(cd "$(dirname "$0")" && pwd)"

echo "▶ Creando directorios en $BASE ..."
mkdir -p "$BASE/db"
mkdir -p "$BASE/sql"
mkdir -p "$BASE/app/api"

echo "▶ Copiando ficheros SQL ..."
cp "$REPO/app/sql/"*.sql "$BASE/sql/"

echo "▶ Copiando aplicación PHP ..."
cp -r "$REPO/app/"* "$BASE/app/"

echo "▶ Copiando configuración Apache ..."
cp "$REPO/docker/apache.conf" "$BASE/apache.conf"

echo ""
echo "✅ Listo. Ahora importa el fichero docker-compose.yml en CasaOS:"
echo "   Apps → Custom Install → Import → selecciona docker-compose.yml"
echo ""
echo "   WebUI estará disponible en:  http://<IP-del-servidor>:8085"
echo "   Usuario por defecto:         admin / admin"
