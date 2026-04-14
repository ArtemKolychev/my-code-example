FROM nginx:stable-alpine AS base

ARG HOST
ARG BE_PORT

RUN apk add --no-cache gettext

# For Let's Encrypt challenge
RUN mkdir -p /var/www/letsencrypt

COPY docker/nginx/certs/ /etc/letsencrypt/live/

ENV HOST=${HOST} BE_PORT=${BE_PORT}

COPY docker/nginx/default.conf /etc/nginx/conf.d/conf.tmp
RUN envsubst "`env | awk -F = '{printf \" $$%s\", $$1}'`" \
          < /etc/nginx/conf.d/conf.tmp \
          > /etc/nginx/conf.d/web.conf && \
    rm -f /etc/nginx/conf.d/default.conf

# dev stage — public/ provided by bind mount at runtime
FROM base AS dev

# prod stage — bake public/ from source into image, use HTTP-only config (Cloudflare handles SSL)
FROM base AS prod
COPY docker/nginx/prod.conf /etc/nginx/conf.d/prod.tmp
RUN envsubst "`env | awk -F = '{printf \" $$%s\", $$1}'`" \
          < /etc/nginx/conf.d/prod.tmp \
          > /etc/nginx/conf.d/web.conf && \
    rm -f /etc/nginx/conf.d/prod.tmp
COPY src/be/public /var/www/html/public
