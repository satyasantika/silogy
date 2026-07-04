FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
      git curl bash zip unzip libzip-dev \
      icu-dev oniguruma-dev libxml2-dev \
      mysql-client mariadb-connector-c-dev \
      libpng-dev jpeg-dev freetype-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
      pdo pdo_mysql mysqli mbstring exif pcntl bcmath gd intl zip opcache

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN mkdir -p storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
