# syntax=docker/dockerfile:1

############################
# 1. PHP dependencies
############################
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist \
        --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

############################
# 2. Frontend assets
############################
FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY . .
# Tailwind @source scans Blade views/SVGs inside vendor/.
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

############################
# 3. Runtime
############################
FROM php:8.4-fpm-alpine AS app

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apk add --no-cache nginx supervisor curl \
    && install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        zip

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN rm -f public/hot \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs storage/app/public storage/app/private bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi

EXPOSE 7080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1:7080/up || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
