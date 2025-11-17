FROM php:8.2-apache

# Instalar dependencias del sistema necesarias para Composer y extensiones
RUN apt-get update && apt-get install -y \
    zip unzip git libpq-dev libzip-dev

# Habilitar extensiones PHP
RUN docker-php-ext-install pdo pdo_pgsql


# Instalar Composer dentro del contenedor
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar archivos del proyecto al contenedor
COPY . /var/www/html

# Ir al directorio del proyecto
WORKDIR /var/www/html

# Instalar dependencias de Composer
RUN composer install --no-interaction --prefer-dist --ignore-platform-req=ext-pgsql

# Dar permisos correctos
RUN chown -R www-data:www-data /var/www/html

# Exponer el puerto (tu backend usa el servidor embebido)
EXPOSE 3000


# Comando de inicio (el mismo que usas local)
CMD ["php", "-S", "0.0.0.0:3000", "Router.php"]