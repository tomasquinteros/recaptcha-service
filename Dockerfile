# Dockerfile para Laravel + Apache + Timeout Alto + Permisos Correctos

# Imagen base
FROM php:8.2-apache

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql zip mbstring exif pcntl bcmath gd

# Configurar Apache
RUN a2enmod rewrite

# Subir Timeout de Apache
RUN echo "Timeout 300" >> /etc/apache2/apache2.conf \
    && echo "KeepAliveTimeout 15" >> /etc/apache2/apache2.conf \
    && echo "MaxKeepAliveRequests 100" >> /etc/apache2/apache2.conf

# Copiar archivos de Laravel al contenedor
COPY . /var/www/html

# Setear permisos correctos para storage y cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Configuración de PHP
RUN echo "max_execution_time = 300" >> /usr/local/etc/php/php.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/php.ini

# Setear el working dir
WORKDIR /var/www/html

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar dependencias de Laravel
RUN composer install --no-dev --optimize-autoloader

# Exponer el puerto 80
EXPOSE 80

# Comando de inicio
CMD ["apache2-foreground"]
