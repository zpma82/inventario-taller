#!/bin/bash
# =============================================================
# Actualizar Inventario Taller — instalación existente en CasaOS
# Uso: sudo bash update-casaos.sh
# =============================================================
set -e
REPO="$(cd "$(dirname "$0")" && pwd)"
CONTAINER=inventaller_app

echo "▶ Copiando ficheros directamente al contenedor..."
if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    docker cp "$REPO/app/api/config.php"       "$CONTAINER:/var/www/html/api/config.php"
    docker cp "$REPO/app/api/auth.php"         "$CONTAINER:/var/www/html/api/auth.php"
    docker cp "$REPO/app/api/backup.php"       "$CONTAINER:/var/www/html/api/backup.php"
    docker cp "$REPO/app/api/equipos.php"      "$CONTAINER:/var/www/html/api/equipos.php"
    docker cp "$REPO/app/api/movimientos.php"  "$CONTAINER:/var/www/html/api/movimientos.php"
    docker cp "$REPO/app/api/ubicaciones.php"  "$CONTAINER:/var/www/html/api/ubicaciones.php"
    docker cp "$REPO/app/api/catalogos.php"    "$CONTAINER:/var/www/html/api/catalogos.php"
    docker cp "$REPO/app/api/.htaccess"        "$CONTAINER:/var/www/html/api/.htaccess"
    docker cp "$REPO/app/index.html"           "$CONTAINER:/var/www/html/index.html"
    docker cp "$REPO/app/img-ies.png"          "$CONTAINER:/var/www/html/img-ies.png"
    echo "   ✅ Ficheros copiados al contenedor"
else
    echo "   ❌ Contenedor $CONTAINER no está corriendo"
    exit 1
fi

echo ""
echo "▶ Verificando sintaxis PHP..."
if docker exec "$CONTAINER" php -l /var/www/html/api/auth.php 2>&1 | grep -q "No syntax errors"; then
    echo "   ✅ auth.php OK"
else
    echo "   ❌ auth.php tiene errores de sintaxis:"
    docker exec "$CONTAINER" php -l /var/www/html/api/auth.php
    exit 1
fi

echo ""
echo "✅ Actualización completada."
