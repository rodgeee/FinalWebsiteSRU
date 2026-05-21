FROM php:8.3-fpm AS builder

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    nodejs \
    npm \
    libicu-dev \
    openssl \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_mysql opcache intl \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./

RUN composer install --no-interaction --no-scripts --no-dev --optimize-autoloader

COPY . .

# Build-time env + JWT keys (runtime env comes from docker-compose)
COPY .env.docker-build .env
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_SECRET=build-time-secret \
    DATABASE_URL="mysql://build:build@127.0.0.1:3306/build?serverVersion=8.0.32&charset=utf8mb4"

RUN mkdir -p config/jwt \
    && openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 \
    && openssl pkey -in config/jwt/private.pem -pubout -out config/jwt/public.pem \
    && chmod 644 config/jwt/public.pem \
    && chmod 600 config/jwt/private.pem

# Build frontend assets needed by the Symfony templates
RUN npm install --no-progress \
    && npm run build:tailwind \
    && rm -rf node_modules package-lock.json \
    && mkdir -p public/styles public/img \
    && cp -R assets/styles/* public/styles/ \
    && cp -R assets/img/* public/img/

# --no-scripts: skip cache:clear (needs a live DB and full .env at build time)
RUN composer install --no-interaction --no-dev --optimize-autoloader --no-ansi --no-scripts \
    && php bin/console importmap:install --no-interaction \
    && php bin/console assets:install public --no-interaction \
    && php bin/console asset-map:compile --no-interaction

FROM php:8.3-fpm AS runtime

WORKDIR /app

RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_mysql opcache intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /app /app

# Runtime must use platform env (Railway), not the build-time .env file
RUN rm -f /app/.env /app/.env.local /app/.env.local.php

# Explicit var subdirs + ownership so PHP-FPM (www-data) can write cache/logs
RUN mkdir -p /app/var/cache /app/var/log /app/var/cache/prod \
    && chown -R www-data:www-data /app/var \
    && chmod -R 775 /app/var

COPY nginx-main.conf /etc/nginx/nginx.conf

RUN rm -rf /etc/nginx/conf.d/* /etc/nginx/sites-enabled /etc/nginx/sites-available
COPY nginx.conf /etc/nginx/conf.d/symfony.conf

COPY entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

HEALTHCHECK --interval=15s --timeout=5s --start-period=40s --retries=3 \
    CMD sh -c 'curl -f "http://127.0.0.1:${PORT:-80}/" || exit 1'

# Railway sets PORT at runtime; entrypoint configures nginx to match
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
