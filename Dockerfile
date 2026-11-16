# syntax=docker/dockerfile:1

# ---------- Base : PHP-FPM + extension PostgreSQL ----------
# OPcache est intégré à PHP 8.5 : rien à installer.
FROM php:8.5-fpm-alpine AS base
RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .build-deps postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && apk del .build-deps

# ---------- Construction : dépendances de production seulement ----------
FROM base AS build
RUN apk add --no-cache unzip
COPY --from=composer:2.11 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------- Application (php-fpm, sans root) ----------
FROM base AS app
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-oep.ini"
WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /app ./
COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/entrypoint
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------- Serveur HTTP (nginx sans root) ----------
FROM nginxinc/nginx-unprivileged:1.31-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 8080
