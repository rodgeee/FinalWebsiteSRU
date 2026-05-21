#!/bin/bash
set -e

PORT="${PORT:-80}"
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/conf.d/symfony.conf
echo "Nginx will listen on port ${PORT}"

# Use Railway/platform env vars, not the build-time .env baked into the image
rm -f /app/.env /app/.env.local /app/.env.local.php

echo "Clearing Symfony cache..."
php bin/console cache:clear --env="${APP_ENV:-prod}" --no-debug

echo "Warming up Symfony cache..."
php bin/console cache:warmup --env="${APP_ENV:-prod}" --no-debug

# Ensure runtime user can write cache/log directories after CLI commands run as root
chown -R www-data:www-data var/cache var/log || true
chmod -R 775 var/cache var/log || true

if [ -n "${DATABASE_URL}" ]; then
    echo "Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

    if [ "${SEED_DEMO_PRODUCTS:-0}" = "1" ]; then
        echo "Seeding demo products (only when catalog is empty)..."
        php bin/console app:seed-demo-products --no-interaction
    fi
else
    echo "WARNING: DATABASE_URL is not set — skipping migrations and product seed." >&2
fi

echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting Nginx..."
exec nginx -g "daemon off;"
