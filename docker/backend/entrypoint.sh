#!/bin/sh
# Prepares the Laravel app on container start, then runs the given command.
set -e

# Run on every start so packages added since the last start get installed.
# When vendor/ already matches composer.lock, this finishes quickly without downloads.
composer install --no-interaction

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# Docker Compose waits for MariaDB to be healthy before starting this container.
php artisan migrate --force

exec "$@"
