#!/bin/sh
set -e

if [ "${APP_ENV}" = "prod" ]; then
  php /var/www/html/bin/console cache:clear --no-warmup || true
  php /var/www/html/bin/console cache:warmup || true
fi

exec "$@"
