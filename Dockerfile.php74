FROM php:7.4-fpm

RUN mkdir -p /var/www/audiid_gl

RUN apt-get update && apt-get install -y wget

RUN apt-get update && apt-get install -y \
    libzip-dev \
    curl \
    zip \
    unzip \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    locales 

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN docker-php-ext-configure gd --with-jpeg
RUN docker-php-ext-install gd

RUN apt-get update \
    && apt-get install -y nginx \
    supervisor 

RUN rm /etc/nginx/sites-available/default

COPY default /etc/nginx/sites-available/default

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN sh -c "wget http://getcomposer.org/composer.phar && chmod a+x composer.phar && mv composer.phar /usr/local/bin/composer"

WORKDIR /var/www/audiid_gl
COPY . /var/www/audiid_gl

RUN mkdir -p /var/www/audiid_gl/public/imagenes/listas_chequeo \
             /var/www/audiid_gl/public/imagenes/logos_empresariales \
             /var/www/audiid_gl/public/imagenes/modelos \
             /var/www/audiid_gl/public/imagenes/usuarios

RUN composer install --no-dev --ignore-platform-reqs

RUN chmod -R 777 /var/www/audiid_gl/storage
RUN chmod -R 777 /var/www/audiid_gl/public

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]