# ── base: shared PHP extensions + composer ───────────────────────────────────
FROM php:8.4-fpm AS base
WORKDIR /var/www/html
ARG PUID=1000
ARG PGID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libpq-dev libzip-dev libonig-dev libxml2-dev \
    zlib1g-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    librabbitmq-dev \
  && docker-php-ext-install intl pdo pdo_pgsql zip opcache xml exif \
  && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  && docker-php-ext-install -j$(nproc) gd \
  && git clone --depth 1 --branch v2.1.2 https://github.com/php-amqp/php-amqp.git /tmp/php-amqp \
  && cd /tmp/php-amqp && phpize && ./configure && make -j$(nproc) && make install \
  && rm -rf /tmp/php-amqp \
  && docker-php-ext-enable amqp \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

ENV COMPOSER_HOME=/var/www/.composer
ENV PATH="$COMPOSER_HOME/vendor/bin:$PATH"

RUN groupmod -o -g ${PGID} www-data \
  && usermod -o -u ${PUID} -g ${PGID} www-data \
  && mkdir -p /var/www/.composer \
  && chown -R www-data:www-data /var/www

# ── dev: xDebug + symfony-cli ────────────────────────────────────────────────
FROM base AS dev
ARG xDEBUG_CLIENT_HOST=host.docker.internal
ARG xDEBUG_CLIENT_PORT=9003

RUN git clone --depth 1 --branch 3.4.1 https://github.com/xdebug/xdebug.git /tmp/xdebug \
  && cd /tmp/xdebug && phpize && ./configure && make -j$(nproc) && make install \
  && rm -rf /tmp/xdebug \
  && docker-php-ext-enable xdebug

COPY docker/php/xDebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.client_host=${xDEBUG_CLIENT_HOST}" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.client_port=${xDEBUG_CLIENT_PORT}" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN ARCH=$(uname -m | sed 's/x86_64/amd64/' | sed 's/aarch64/arm64/') \
  && curl -L "https://github.com/symfony-cli/symfony-cli/releases/latest/download/symfony-cli_linux_${ARCH}.tar.gz" \
     | tar -xz -C /usr/local/bin symfony \
  && symfony check:requirements

COPY --chown=www-data:www-data docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
USER www-data
ENTRYPOINT ["/entrypoint.sh"]

# ── prod: baked source + deps ────────────────────────────────────────────────
FROM base AS prod
COPY --chown=www-data:www-data src/be /var/www/html
USER www-data
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --prefer-dist
COPY --chown=www-data:www-data docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
