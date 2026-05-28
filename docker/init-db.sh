#!/bin/bash
HOST="${DB_HOST:-db}"
USER="${DB_USER:-almacen_local}"
PASS="${DB_PASS:-CambiaEstaPassword1!}"
NAME="${DB_NAME:-inventaller}"
ROOT_PASS="RootPass_Cambia1!"
# --skip-ssl funciona tanto en MySQL como en MariaDB client
MYSQL="mysql --skip-ssl"

echo "[init-db] Esperando a MySQL en $HOST..."
for i in $(seq 1 30); do
  if mysqladmin --skip-ssl ping -h"$HOST" -uroot -p"$ROOT_PASS" --silent 2>/dev/null; then
    echo "[init-db] MySQL listo."
    break
  fi
  echo "[init-db] Intento $i/30..."
  sleep 3
done

TABLES=$($MYSQL -h"$HOST" -u"$USER" -p"$PASS" "$NAME" -e "SHOW TABLES;" 2>/dev/null | wc -l)
if [ "$TABLES" -gt 1 ]; then
  echo "[init-db] BD ya inicializada ($TABLES tablas). Saltando."
  exit 0
fi

echo "[init-db] Aplicando SQLs..."
for SQL in /var/www/html/sql/0*.sql; do
  echo "[init-db] → $(basename $SQL)"
  $MYSQL -h"$HOST" -uroot -p"$ROOT_PASS" "$NAME" < "$SQL" 2>&1 \
  || $MYSQL -h"$HOST" -u"$USER" -p"$PASS" "$NAME" < "$SQL" 2>&1 \
  || true
done
echo "[init-db] ✅ BD inicializada."
