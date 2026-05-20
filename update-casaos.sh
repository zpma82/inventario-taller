#!/bin/bash
# =============================================================
# Actualizar Inventario Taller en CasaOS desde el repositorio
# Uso: bash update-casaos.sh
# =============================================================
set -e

BASE=/DATA/AppData/inventaller
REPO="$(cd "$(dirname "$0")" && pwd)"

echo "▶ Actualizando ficheros PHP y HTML..."
cp -r "$REPO/app/"* "$BASE/app/"
cp "$REPO/docker/apache.conf" "$BASE/apache.conf"

echo "▶ Copiando SQL de migraciones nuevas..."
cp "$REPO/app/sql/"*.sql "$BASE/sql/"

echo ""
echo "✅ Ficheros actualizados en $BASE/app/"
echo "   El contenedor los usa directamente (volumen montado)."
echo "   No es necesario reiniciar."
echo ""
echo "   Comprueba la versión en: http://<IP>:8085/api/diagnostico.php"
