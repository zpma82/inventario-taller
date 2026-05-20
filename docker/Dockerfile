FROM php:8.2-apache

# Instalar pdo_mysql y habilitar módulos Apache necesarios
RUN docker-php-ext-install pdo pdo_mysql \
 && a2enmod rewrite headers cgid \
 && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Directorio de trabajo
WORKDIR /var/www/html

EXPOSE 80
