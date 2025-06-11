FROM php:8.1-fpm

# Cài extensions cần thiết cho Lumen
RUN apt-get update && apt-get install -y \
    git zip unzip libzip-dev libpng-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
