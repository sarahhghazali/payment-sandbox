# ---- Composer dependencies ----
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
# Install production dependencies only; scripts need the app code, so skip them.
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --no-progress

# ---- Application image ----
FROM php:8.4-fpm-alpine

# pdo_mysql for the database, opcache because PHP without it is slow,
# bcmath for integer-safe money maths.
RUN apk add --no-cache mysql-client \
    && docker-php-ext-install pdo_mysql opcache bcmath

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

# Composer scripts were skipped above, so finish the autoloader now.
COPY --from=vendor /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
