FROM php:8.2-apache

# Variables para Chrome y Composer
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Argentina/Buenos_Aires
ENV COMPOSER_ALLOW_SUPERUSER=1

# Instalar dependencias del sistema para concurrencia y libs necesarias para Chrome
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    wget \
    gnupg \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    zip \
    libonig-dev \
    libssl-dev \
    libnss3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxrandr2 \
    libxss1 \
    libasound2 \
    libgbm1 \
    libxcb1 \
    libx11-xcb1 \
    libxfixes3 \
    libxtst6 \
    libpangocairo-1.0-0 \
    libpango-1.0-0 \
    libcairo2 \
    fonts-liberation \
    libappindicator3-1 \
    xdg-utils \
    jq \
    supervisor \
    htop \
    && docker-php-ext-install pdo pdo_mysql zip gd pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Descargar e instalar Google Chrome desde zip (chrome-for-testing)
RUN wget -O /tmp/chrome-linux64.zip https://storage.googleapis.com/chrome-for-testing-public/139.0.7258.66/linux64/chrome-linux64.zip && \
    unzip /tmp/chrome-linux64.zip -d /opt/ && \
    rm /tmp/chrome-linux64.zip && \
    ln -s /opt/chrome-linux64/chrome /usr/bin/google-chrome

# Descargar e instalar ChromeDriver desde zip (chrome-for-testing)
RUN wget -O /tmp/chromedriver-linux64.zip https://storage.googleapis.com/chrome-for-testing-public/139.0.7258.66/linux64/chromedriver-linux64.zip && \
    unzip /tmp/chromedriver-linux64.zip -d /tmp/ && \
    mv /tmp/chromedriver-linux64/chromedriver /usr/local/bin/chromedriver && \
    chmod +x /usr/local/bin/chromedriver && \
    rm -rf /tmp/chromedriver-linux64.zip /tmp/chromedriver-linux64

# Configurar Apache para Laravel
RUN a2enmod rewrite
COPY ./apache/laravel.conf /etc/apache2/sites-available/000-default.conf

# Copiar código de Laravel
WORKDIR /var/www/html
COPY . /var/www/html

# Configurar Git safe directory y permisos
RUN git config --global --add safe.directory /var/www/html && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Configuraciones de PHP para alta concurrencia
RUN echo "memory_limit=512M" >> /usr/local/etc/php/php.ini && \
    echo "max_execution_time=120" >> /usr/local/etc/php/php.ini && \
    echo "max_input_time=60" >> /usr/local/etc/php/php.ini && \
    echo "post_max_size=50M" >> /usr/local/etc/php/php.ini && \
    echo "upload_max_filesize=50M" >> /usr/local/etc/php/php.ini && \
    echo "opcache.enable=1" >> /usr/local/etc/php/php.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/php.ini

# Configurar límites del sistema
RUN echo "fs.file-max = 65536" >> /etc/sysctl.conf && \
    echo "* soft nofile 65536" >> /etc/security/limits.conf && \
    echo "* hard nofile 65536" >> /etc/security/limits.conf

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-plugins --no-scripts

# Ejecutar scripts post-install si es necesario
RUN composer run-script post-autoload-dump --no-interaction || true

# Exponer puerto
EXPOSE 80

CMD ["apache2-foreground"]
