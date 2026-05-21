#!/bin/sh
set -e

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    echo "Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

php-fpm -D
exec nginx -g 'daemon off;'
