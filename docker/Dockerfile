# ──────────────────────────────────────────────────────────────
# Inventario Taller — PHP 8.2 + Apache
# Build context: raíz del proyecto (docker-compose lo gestiona)
# ──────────────────────────────────────────────────────────────
FROM php:8.2-apache

# Extensión PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# Config Apache (contexto = raíz del proyecto)
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Código de la aplicación
COPY app/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html

EXPOSE 80
