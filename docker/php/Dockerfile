FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    linux-headers \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip \
    opcache \
    pcntl \
    sockets

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app

WORKDIR /var/www/app

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

USER app

CMD ["php-fpm"]
