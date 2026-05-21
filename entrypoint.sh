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

# Railway MySQL plugin exposes MYSQL_URL; Symfony expects DATABASE_URL (not localhost).
if [ -n "${MYSQL_URL}" ]; then
    export DATABASE_URL="${MYSQL_URL}"
elif echo "${DATABASE_URL:-}" | grep -qE '@(127\.0\.0\.1|localhost)([:/]|$)'; then
    echo "ERROR: DATABASE_URL points to localhost — MySQL is not in this container." >&2
    echo "  Fix: Railway → MySQL service → Connect → copy URL → app Variables → DATABASE_URL" >&2
    echo "  Or link the MySQL service so MYSQL_URL appears on this app." >&2
    unset DATABASE_URL
fi
if [ -n "${DATABASE_URL}" ] && ! echo "${DATABASE_URL}" | grep -q 'serverVersion='; then
    if echo "${DATABASE_URL}" | grep -q '?'; then
        export DATABASE_URL="${DATABASE_URL}&serverVersion=8.0.32&charset=utf8mb4"
    else
        export DATABASE_URL="${DATABASE_URL}?serverVersion=8.0.32&charset=utf8mb4"
    fi
fi
# PHP-FPM does not always inherit shell env; write resolved values into .env for web requests.
if [ -n "${DATABASE_URL}" ]; then
    grep -v '^DATABASE_URL=' /app/.env > /app/.env.runtime 2>/dev/null || cp /app/.env /app/.env.runtime
    printf 'DATABASE_URL="%s"\n' "${DATABASE_URL}" >> /app/.env.runtime
    mv /app/.env.runtime /app/.env
    echo "Database host configured for Symfony (from Railway MYSQL_URL / DATABASE_URL)."
fi
if [ -n "${APP_SECRET}" ] && [ "${APP_SECRET}" != "unsafe-default-set-APP_SECRET-in-railway" ]; then
    grep -v '^APP_SECRET=' /app/.env > /app/.env.runtime 2>/dev/null || cp /app/.env /app/.env.runtime
    printf 'APP_SECRET=%s\n' "${APP_SECRET}" >> /app/.env.runtime
    mv /app/.env.runtime /app/.env
fi

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

mkdir -p var/sessions
chown -R www-data:www-data var/cache var/log var/sessions 2>/dev/null || true
chmod -R 775 var/cache var/log var/sessions 2>/dev/null || true

# Wait for DB helper: tries to connect using PHP PDO (uses DATABASE_URL)
wait_for_db() {
    if [ -z "${DATABASE_URL}" ]; then
        return 1
    fi
    echo "Waiting for database to become available..."
    retries=20
    delay=3
    while [ $retries -gt 0 ]; do
        php -r "try { \$url=getenv('DATABASE_URL'); if (!\$url) exit(2); \$p=parse_url(\$url); if (!isset(\$p['host'])) exit(2); \$host=\$p['host']; \$port=(isset(\$p['port'])?\$p['port']:3306); \$user=isset(\$p['user'])?\$p['user']:null; \$pass=isset(\$p['pass'])?\$p['pass']:null; \$dsn='mysql:host='.\$host.';port='.\$port.';charset=utf8mb4'; \$pdo=new PDO(\$dsn,\$user,\$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]); \$pdo->query('SELECT 1'); echo 'ok'; } catch (Exception \$e) { fwrite(STDERR, \$e->getMessage()); exit(1);}"
        rc=$?
        if [ $rc -eq 0 ]; then
            echo "Database ready"
            return 0
        fi
        echo "DB not ready yet (exit $rc), sleeping $delay s..."
        retries=$((retries-1))
        sleep $delay
    done
    echo "Timed out waiting for database." >&2
    return 1
}

if [ -n "${DATABASE_URL}" ]; then
    if wait_for_db; then
        echo "Running database migrations..."
        if ! run_console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
            echo "WARNING: migrations failed — retrying once after short wait." >&2
            sleep 5
            if ! run_console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
                echo "WARNING: migrations failed — check DATABASE_URL and MySQL connectivity." >&2
            else
                echo "Verifying staff accounts can log in..."
                run_console app:verify-staff-for-login --no-interaction || true
            fi
        else
            echo "Verifying staff accounts can log in..."
            run_console app:verify-staff-for-login --no-interaction || true
        fi
    else
        echo "WARNING: DATABASE_URL present but DB unreachable — skipping migrations." >&2
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
