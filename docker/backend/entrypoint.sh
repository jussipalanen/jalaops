#!/bin/sh
# Prepares the Laravel app on container start, then runs the given command.
set -e

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# Docker Compose waits for MariaDB to be healthy before starting this container.
php artisan migrate --force

exec "$@"
