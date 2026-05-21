#!/bin/bash
set -e

PORT="${PORT:-80}"
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/conf.d/symfony.conf
echo "Nginx will listen on port ${PORT}"

echo "Clearing Symfony cache..."
php bin/console cache:clear --env="${APP_ENV:-prod}" --no-debug

echo "Warming up Symfony cache..."
php bin/console cache:warmup --env="${APP_ENV:-prod}" --no-debug

# Ensure runtime user can write cache/log directories after CLI commands run as root
chown -R www-data:www-data var/cache var/log || true
chmod -R 775 var/cache var/log || true

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    echo "Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting Nginx..."
exec nginx -g "daemon off;"
