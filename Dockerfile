FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS dependencies

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev \
    && docker-php-ext-install bcmath intl pdo_mysql zip \
    && a2enmod rewrite \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && printf '%s\n' 'ServerName localhost' '<Directory /var/www/html/public>' '    FallbackResource /index.php' '</Directory>' > /etc/apache2/conf-available/covershopping.conf \
    && a2enconf covershopping \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . ./
COPY --from=assets /app/public/build ./public/build
COPY --from=dependencies /app/vendor ./vendor
COPY docker/entrypoint.sh /usr/local/bin/covershopping-entrypoint

RUN rm -f bootstrap/cache/*.php \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs \
    && sed -i 's/\r$//' /usr/local/bin/covershopping-entrypoint \
    && chmod +x /usr/local/bin/covershopping-entrypoint \
    && chown -R www-data:www-data bootstrap/cache storage

ENTRYPOINT ["covershopping-entrypoint"]
CMD ["apache2-foreground"]
