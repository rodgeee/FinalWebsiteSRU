#!/bin/bash
# Do not use "set -e" — a failed migration or cache command must not kill the container.

PORT="${PORT:-80}"
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/conf.d/symfony.conf
echo "Nginx will listen on port ${PORT}"

# Keep /app/.env (required by Symfony); remove local overrides only
rm -f /app/.env.local /app/.env.local.php

# Railway/OS variables override .env defaults (Symfony does not replace existing env vars)
export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"
if [ -z "${APP_SECRET}" ]; then
    echo "WARNING: APP_SECRET is not set — set it in Railway Variables." >&2
    export APP_SECRET="unsafe-default-set-APP_SECRET-in-railway"
fi
export MAILER_DSN="${MAILER_DSN:-null://null}"
export MAILER_FROM_ADDRESS="${MAILER_FROM_ADDRESS:-noreply@localhost}"
export MESSENGER_TRANSPORT_DSN="${MESSENGER_TRANSPORT_DSN:-sync://}"
export CORS_ALLOW_ORIGIN="${CORS_ALLOW_ORIGIN:-^.*$}"
export JWT_PASSPHRASE="${JWT_PASSPHRASE:-}"
if [ -z "${DEFAULT_URI}" ] && [ -n "${RAILWAY_PUBLIC_DOMAIN}" ]; then
    export DEFAULT_URI="https://${RAILWAY_PUBLIC_DOMAIN}"
fi
export DEFAULT_URI="${DEFAULT_URI:-http://localhost}"
export APP_ROOT_TO_ADMIN="${APP_ROOT_TO_ADMIN:-0}"

run_console() {
    php bin/console "$@" --env="${APP_ENV}" --no-debug
}

echo "Clearing Symfony cache..."
if ! run_console cache:clear; then
    echo "WARNING: cache:clear failed — continuing startup." >&2
fi

echo "Warming up Symfony cache..."
if ! run_console cache:warmup; then
    echo "WARNING: cache:warmup failed — running cache:clear again." >&2
    run_console cache:clear || true
fi

chown -R www-data:www-data var/cache var/log 2>/dev/null || true
chmod -R 775 var/cache var/log 2>/dev/null || true

if [ -n "${DATABASE_URL}" ]; then
    echo "Running database migrations..."
    if ! run_console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
        echo "WARNING: migrations failed — check DATABASE_URL and MySQL connectivity." >&2
    fi

    if [ "${SEED_DEMO_PRODUCTS:-0}" = "1" ]; then
        echo "Seeding demo products (only when catalog is empty)..."
        run_console app:seed-demo-products --no-interaction || true
    fi
else
    echo "WARNING: DATABASE_URL is not set — skipping migrations and product seed." >&2
fi

echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting Nginx..."
exec nginx -g "daemon off;"
