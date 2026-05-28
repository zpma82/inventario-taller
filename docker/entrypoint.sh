#!/bin/bash
set -e

# Inicializar la BD en background (no bloquea Apache)
/usr/local/bin/init-db.sh &

# Arrancar Apache en foreground
exec apache2-foreground
