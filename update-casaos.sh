#!/bin/bash
# =============================================================
# Actualizar Inventario Taller en CasaOS desde el repositorio
# Uso: sudo bash update-casaos.sh
# =============================================================
set -e

BASE=/DATA/AppData/inventaller
REPO="$(cd "$(dirname "$0")" && pwd)"

echo "▶ Actualizando ficheros PHP y HTML..."
cp -fv "$REPO/app/api/config.php"    "$BASE/app/api/config.php"
cp -fv "$REPO/app/api/auth.php"      "$BASE/app/api/auth.php"
cp -fv "$REPO/app/api/backup.php"    "$BASE/app/api/backup.php"
cp -fv "$REPO/app/api/equipos.php"   "$BASE/app/api/equipos.php"
cp -fv "$REPO/app/api/movimientos.php" "$BASE/app/api/movimientos.php"
cp -fv "$REPO/app/api/ubicaciones.php" "$BASE/app/api/ubicaciones.php"
cp -fv "$REPO/app/api/catalogos.php"  "$BASE/app/api/catalogos.php"
cp -fv "$REPO/app/api/.htaccess"     "$BASE/app/api/.htaccess"
cp -fv "$REPO/app/index.html"        "$BASE/app/index.html"
cp -fv "$REPO/docker/apache.conf"    "$BASE/apache.conf"

echo ""
echo "▶ Copiando SQL..."
cp -fv "$REPO/app/sql/"*.sql "$BASE/sql/"

echo ""
echo "▶ Verificando auth.php en el servidor..."
if grep -q 'crear_operario' "$BASE/app/api/auth.php"; then
    echo "   ✅ crear_operario: PRESENTE"
else
    echo "   ❌ crear_operario: AUSENTE — algo falló"
    exit 1
fi

if python3 -c "
c = open('$BASE/app/api/auth.php').read()
bad = [i+1 for i,l in enumerate(c.split('\n')) if '\u201c' in l or '\u201d' in l]
if bad: print('COMILLAS MALAS en líneas:', bad); exit(1)
print('   ✅ Sin comillas tipográficas')
"; then
    echo ""
else
    echo "   ❌ El archivo tiene comillas tipográficas — corrigiendo..."
    python3 -c "
f='$BASE/app/api/auth.php'
c=open(f).read().replace('\u201c','\"').replace('\u201d','\"').replace('\u2018',\"'\").replace('\u2019',\"'\")
open(f,'w').write(c)
print('   ✅ Corregido')
"
fi

echo ""
echo "✅ Actualización completada."
echo "   Prueba: curl -s -X POST http://localhost:8085/api/auth.php \\"
echo "     -H 'Content-Type: application/json' \\"
echo "     -d '{\"accion\":\"login\",\"usuario\":\"admin\",\"password\":\"1234\"}'"
