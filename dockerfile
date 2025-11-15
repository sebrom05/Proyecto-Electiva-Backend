# Imagen base con PHP 8.1 y CLI para ejecutar php -S
FROM php:8.1-cli

# Instalar extensiones necesarias para PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql

# Crear directorio de trabajo
WORKDIR /app

# Copiar archivos del backend
COPY . /app

# Copiar Composer desde la imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar dependencias PHP (si existe composer.json)
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Exponer el puerto (Azure ingresa por $PORT automáticamente)
EXPOSE 8080

# Comando para ejecutar el servidor PHP embebido
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t public public/index.php"]
