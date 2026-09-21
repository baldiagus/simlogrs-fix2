FROM php:8.3-fpm-alpine

# Pasang dependency sistem & build tools
RUN apk add --no-cache \
    curl \
    git \
    nodejs \
    npm \
    linux-headers \
    libpq-dev \
    libzip-dev \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    bash

# Pasang ekstensi PHP via mlocati/docker-php-extension-installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    bcmath \
    gd \
    zip \
    intl \
    opcache \
    pcntl

# Salin Composer binary resmi
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Konfigurasi OPcache
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# Konfigurasi Upload & Memory
RUN echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize=50M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
