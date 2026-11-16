FROM php:8.3-fpm-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# only what the code uses: PostgreSQL through PDO, and opcache
RUN apk add --no-cache postgresql-libs \
    && apk add --no-cache --virtual .build-deps postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" opcache pgsql pdo_pgsql \
    && apk del .build-deps

COPY docker/php/app.ini /usr/local/etc/php/conf.d/app.ini

COPY . .
