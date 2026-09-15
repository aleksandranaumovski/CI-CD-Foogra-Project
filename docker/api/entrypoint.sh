#!/bin/sh
#
# Entrypoint for the API-only image (docker/api.Dockerfile). Trimmed version
# of docker/entrypoint.sh: no Render PORT injection (nginx.conf is static,
# fixed at 8000 for Compose/Kubernetes), everything else — key warning,
# runtime dirs, config caching, migrations/seeders — is unchanged.
#
set -e

cd /var/www/html

if [ -z "${APP_KEY}" ]; then
    echo "WARNING: APP_KEY is not set. Generating a throwaway key for this"
    echo "         container — sessions and encrypted data will not survive a"
    echo "         restart. Set APP_KEY via docker-compose.yml or the api-secret"
    echo "         Kubernetes Secret: php artisan key:generate --show"
    export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [ ! -L public/storage ]; then
    php artisan storage:link --quiet || true
fi

php artisan config:clear --quiet
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet

chown -R www-data:www-data storage bootstrap/cache

# Off by default so a redeploy never touches the schema unexpectedly; set
# RUN_MIGRATIONS=true (docker-compose.yml / api-config ConfigMap) to migrate
# on every boot.
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force --isolated || php artisan migrate --force
fi

if [ "${RUN_SEEDERS}" = "true" ]; then
    echo "Running seeders..."
    php artisan db:seed --force
fi

exec "$@"
