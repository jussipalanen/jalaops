# Production image for the Laravel backend (used by Render).
# Build from the repository root: docker build -f docker/production/Dockerfile .
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# Install dependencies first so this layer is cached between code changes.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY backend/ ./
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache database

COPY docker/production/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DEMO_MODE=true \
    PHP_CLI_SERVER_WORKERS=4

USER www-data

EXPOSE 10000

CMD ["start.sh"]
