FROM composer:2.8 AS vendor_builder
WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.5-fpm-alpine AS runtime

RUN apk add --no-cache \
    libpq-dev \
    unzip \
    git

RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    bcmath

WORKDIR /var/www/html
COPY --from=vendor_builder /var/www/html/vendor ./vendor
COPY . .

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 755 /var/www/html

USER www-data
EXPOSE 9000

CMD ["php-fpm"]