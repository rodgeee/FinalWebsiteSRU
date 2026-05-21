FROM php:8.3-fpm AS builder

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    nodejs \
    npm \
    && docker-php-ext-install pdo pdo_mysql opcache \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./

RUN composer install --no-interaction --no-scripts --no-dev --optimize-autoloader

COPY . .

RUN if [ ! -f /app/.env ]; then \
    printf 'APP_ENV=prod\nAPP_DEBUG=false\nAPP_SECRET=ChangeMe\n' > /app/.env; \
    fi

RUN composer install --no-interaction --no-dev --optimize-autoloader --no-ansi
RUN php bin/console importmap:install --no-interaction
RUN php bin/console cache:warmup --env=prod --no-debug

FROM php:8.3-fpm AS runtime

WORKDIR /app

RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    && docker-php-ext-install pdo pdo_mysql opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /app /app

RUN mkdir -p /app/var && \
    chown -R www-data:www-data /app && \
    chmod -R 755 /app && \
    chmod -R 775 /app/var

COPY nginx-main.conf /etc/nginx/nginx.conf

RUN rm -rf /etc/nginx/conf.d/* /etc/nginx/sites-enabled /etc/nginx/sites-available
COPY nginx.conf /etc/nginx/conf.d/symfony.conf

COPY entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

HEALTHCHECK --interval=15s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
