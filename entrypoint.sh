#!/bin/bash
set -e

echo "Warming up Symfony cache..."
php bin/console cache:warmup --env="${APP_ENV:-prod}" --no-debug

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    echo "Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

echo "Starting PHP-FPM..."
php-fpm -F &
PHP_PID=$!

echo "Waiting for PHP-FPM to start..."
sleep 2

echo "Starting Nginx..."
nginx -g "daemon off;"

wait $PHP_PID