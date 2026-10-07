#!/bin/sh
# Starts the backend in production.
#
# DEMO_MODE=true  (default) Local demo data: every start creates a fresh SQLite
#                 database with the demo requests, so changes last only until
#                 the next restart. DB_* settings are ignored.
# DEMO_MODE=false Uses the database configured with DB_CONNECTION, DB_HOST etc.
#                 and keeps its data. SEED_DEMO_DATA=true adds the demo
#                 requests once, into an empty table.
set -e

# Without a configured key, use a random one. Fine for the demo: nothing
# encrypted needs to survive a restart.
if [ -z "$APP_KEY" ]; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
fi

if [ "$DEMO_MODE" = "true" ]; then
    export DB_CONNECTION=sqlite
    export DB_DATABASE="$PWD/database/database.sqlite"
    : > "$DB_DATABASE"
    php artisan migrate:fresh --seed --force
else
    php artisan migrate --force

    if [ "$SEED_DEMO_DATA" = "true" ]; then
        php artisan db:seed --force
    fi
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
