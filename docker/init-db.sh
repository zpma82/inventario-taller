#!/bin/bash
# Script de inicialización de BD — se ejecuta en el contenedor app
# Espera a que MySQL esté listo y aplica los SQLs si la BD está vacía

set -e
HOST="${DB_HOST:-db}"
USER="${DB_USER:-almacen_local}"
PASS="${DB_PASS:-CambiaEstaPassword1!}"
NAME="${DB_NAME:-inventaller}"
ROOT_PASS="RootPass_Cambia1!"

echo "[init-db] Esperando a MySQL en $HOST..."
for i in $(seq 1 30); do
  if mysqladmin ping -h"$HOST" -uroot -p"$ROOT_PASS" --silent 2>/dev/null; then
    break
  fi
  echo "[init-db] Intento $i/30..."
  sleep 3
done

# Comprobar si la BD ya tiene tablas
TABLES=$(mysql -h"$HOST" -u"$USER" -p"$PASS" "$NAME" -e "SHOW TABLES;" 2>/dev/null | wc -l)
if [ "$TABLES" -gt 1 ]; then
  echo "[init-db] BD ya inicializada ($TABLES tablas). Saltando."
  exit 0
fi

echo "[init-db] Inicializando BD con los ficheros SQL..."
for SQL in /var/www/html/sql/*.sql; do
  echo "[init-db] Aplicando $SQL..."
  mysql -h"$HOST" -uroot -p"$ROOT_PASS" "$NAME" < "$SQL" 2>/dev/null || \
  mysql -h"$HOST" -u"$USER" -p"$PASS"   "$NAME" < "$SQL" || true
done
echo "[init-db] BD inicializada correctamente."
