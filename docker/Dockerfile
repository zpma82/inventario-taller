FROM php:8.2-apache

# Instalar cliente MySQL, extensiones PHP, tzdata y módulos Apache
RUN apt-get update -qq \
 && apt-get install -y --no-install-recommends default-mysql-client tzdata \
 && docker-php-ext-install pdo pdo_mysql \
 && a2enmod rewrite headers cgid \
 && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && rm -rf /var/lib/apt/lists/*

# Zona horaria Europe/Madrid
ENV TZ=Europe/Madrid
RUN ln -snf /usr/share/zoneinfo/Europe/Madrid /etc/localtime \
 && echo "Europe/Madrid" > /etc/timezone \
 && echo "date.timezone=Europe/Madrid" >> /usr/local/etc/php/conf.d/timezone.ini

# Configuración Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Script de inicialización de BD
COPY docker/init-db.sh /usr/local/bin/init-db.sh

# Aplicación PHP + SQLs
COPY app/ /var/www/html/

# Copiar SQLs también a /var/www/html/sql/ para el script de init
COPY app/sql/ /var/www/html/sql/

RUN chown -R www-data:www-data /var/www/html \
 && chmod +x /usr/local/bin/init-db.sh

# Entrypoint: inicializar BD y luego arrancar Apache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
