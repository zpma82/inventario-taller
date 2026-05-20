#!/bin/bash
# =============================================================
# Actualizar Inventario Taller en CasaOS desde el repositorio
# Uso: sudo bash update-casaos.sh
# =============================================================
set -e

BASE=/DATA/AppData/inventaller
REPO="$(cd "$(dirname "$0")" && pwd)"
CONTAINER=inventaller_app

echo "▶ Actualizando ficheros en el volumen ($BASE)..."
cp -fv "$REPO/app/api/config.php"      "$BASE/app/api/config.php"
cp -fv "$REPO/app/api/auth.php"        "$BASE/app/api/auth.php"
cp -fv "$REPO/app/api/backup.php"      "$BASE/app/api/backup.php"
cp -fv "$REPO/app/api/equipos.php"     "$BASE/app/api/equipos.php"
cp -fv "$REPO/app/api/movimientos.php" "$BASE/app/api/movimientos.php"
cp -fv "$REPO/app/api/ubicaciones.php" "$BASE/app/api/ubicaciones.php"
cp -fv "$REPO/app/api/catalogos.php"   "$BASE/app/api/catalogos.php"
cp -fv "$REPO/app/api/.htaccess"       "$BASE/app/api/.htaccess"
cp -fv "$REPO/app/index.html"          "$BASE/app/index.html"
cp -fv "$REPO/docker/apache.conf"      "$BASE/apache.conf"
cp -fv "$REPO/app/sql/"*.sql           "$BASE/sql/"

echo ""
echo "▶ Copiando directamente al contenedor (evita caché de volumen)..."
if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    docker cp "$REPO/app/api/auth.php"        "$CONTAINER:/var/www/html/api/auth.php"
    docker cp "$REPO/app/api/config.php"      "$CONTAINER:/var/www/html/api/config.php"
    docker cp "$REPO/app/api/backup.php"      "$CONTAINER:/var/www/html/api/backup.php"
    docker cp "$REPO/app/api/equipos.php"     "$CONTAINER:/var/www/html/api/equipos.php"
    docker cp "$REPO/app/api/movimientos.php" "$CONTAINER:/var/www/html/api/movimientos.php"
    docker cp "$REPO/app/api/ubicaciones.php" "$CONTAINER:/var/www/html/api/ubicaciones.php"
    docker cp "$REPO/app/api/catalogos.php"   "$CONTAINER:/var/www/html/api/catalogos.php"
    docker cp "$REPO/app/index.html"          "$CONTAINER:/var/www/html/index.html"
    echo "   ✅ Ficheros copiados al contenedor"
else
    echo "   ⚠️  Contenedor no está corriendo — solo se actualizó el volumen"
fi

echo ""
echo "▶ Verificando sintaxis PHP en el contenedor..."
if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    if docker exec "$CONTAINER" php -l /var/www/html/api/auth.php 2>&1 | grep -q "No syntax errors"; then
        echo "   ✅ auth.php: sintaxis correcta"
    else
        echo "   ❌ auth.php tiene errores de sintaxis"
        docker exec "$CONTAINER" php -l /var/www/html/api/auth.php
        exit 1
    fi
fi

echo ""
echo "✅ Actualización completada."
echo "   Prueba login:"
echo "   curl -s -X POST http://localhost:8085/api/auth.php \\"
echo "     -H 'Content-Type: application/json' \\"
echo "     -d '{\"accion\":\"login\",\"usuario\":\"admin\",\"password\":\"1234\"}'"
