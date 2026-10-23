FROM php:8.2-fpm

RUN mkdir -p /var/www/audiid_gl

RUN apt-get update && apt-get install -y \
    wget \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    locales \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Extensiones de PHP requeridas
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo pdo_mysql zip

# Configuración Nginx y Supervisor
RUN rm -f /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
COPY default /etc/nginx/sites-available/default
RUN ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Instalar Composer oficial
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/audiid_gl
COPY . /var/www/audiid_gl

RUN mkdir -p /var/www/audiid_gl/public/imagenes/listas_chequeo \
             /var/www/audiid_gl/public/imagenes/logos_empresariales \
             /var/www/audiid_gl/public/imagenes/modelos \
             /var/www/audiid_gl/public/imagenes/usuarios \
             /var/www/audiid_gl/storage/framework/views \
             /var/www/audiid_gl/storage/framework/cache \
             /var/www/audiid_gl/storage/framework/sessions \
             /var/www/audiid_gl/storage/logs

# Actualizar dependencias al nuevo composer.json para PHP 8.2 y Laravel 10
RUN composer config --no-interaction policy.advisories.block false
RUN composer update --with-all-dependencies --no-dev --no-interaction --optimize-autoloader --no-audit

RUN chmod -R 777 /var/www/audiid_gl/storage /var/www/audiid_gl/public

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
