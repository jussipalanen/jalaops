# Production image for the Laravel backend (used by Cloud Run and Render).
# Build from the backend directory: docker build backend
FROM php:8.5-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libzip-dev libpng-dev \
    # GD lets dompdf draw PNG images (request signatures) in PDF exports.
    && docker-php-ext-install pdo_mysql zip gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# Install dependencies first so this layer is cached between code changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . ./
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache database

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DEMO_MODE=true \
    PHP_CLI_SERVER_WORKERS=4

USER www-data

# Cloud Run and Render set PORT; start.sh falls back to 10000.
EXPOSE 10000

CMD ["start.sh"]
