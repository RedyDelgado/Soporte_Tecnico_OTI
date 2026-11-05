FROM node:24-alpine AS assets
WORKDIR /app
COPY package*.json vite.config.js ./
RUN npm ci
COPY resources resources
RUN npm run build

FROM php:8.4-apache
RUN apt-get update && apt-get install -y libgmp-dev libzip-dev unzip libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql gmp zip gd \
    && a2enmod rewrite \
    && printf 'upload_max_filesize=12M\npost_max_size=40M\n' > /usr/local/etc/php/conf.d/subidas.ini \
    && sed -i 's#/var/www/html#/var/www/html/public#' /etc/apache2/sites-available/000-default.conf \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build public/build
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache
CMD ["sh", "-c", "php artisan migrate --force && php artisan optimize && php artisan storage:link; apache2-foreground"]
