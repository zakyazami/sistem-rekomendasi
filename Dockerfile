FROM node:24-alpine AS frontend

WORKDIR /var/www/html
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
RUN npm ci && npm run build

FROM composer:2 AS dependencies

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install intl mbstring opcache pcntl pdo_mysql zip

WORKDIR /var/www/html
COPY . .
COPY --from=dependencies /var/www/html/vendor ./vendor
COPY --from=frontend /var/www/html/public/build ./public/build

RUN php artisan package:discover --ansi \
    && php artisan filament:cache-components \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm"]

FROM nginx:1.29-alpine AS web

WORKDIR /var/www/html
COPY --from=app /var/www/html/public ./public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
