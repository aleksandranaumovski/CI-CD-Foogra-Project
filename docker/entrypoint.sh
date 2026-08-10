#!/bin/sh
#
# Boots the container: renders nginx's listen port, prepares Laravel's
# runtime directories, warms the config/route/view caches and (optionally)
# runs migrations before handing over to supervisor.
#
set -e

cd /var/www/html

# ---------------------------------------------------------------- #
# nginx listen port                                                #
# ---------------------------------------------------------------- #
# Render assigns PORT at runtime, so it cannot be baked into the image.
: "${PORT:=8080}"
sed "s|\${PORT}|${PORT}|g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# ---------------------------------------------------------------- #
# Application key                                                  #
# ---------------------------------------------------------------- #
# Generating one here would work, but it would differ on every deploy and
# silently invalidate all sessions and encrypted columns — so warn loudly
# instead of papering over a missing variable.
if [ -z "${APP_KEY}" ]; then
    echo "WARNING: APP_KEY is not set. Generating a throwaway key for this"
    echo "         container — sessions and encrypted data will not survive a"
    echo "         restart. Set APP_KEY in the Render dashboard:"
    echo "           php artisan key:generate --show"
    export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
fi

# ---------------------------------------------------------------- #
# Runtime directories                                              #
# ---------------------------------------------------------------- #
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# public/storage is a symlink into storage/app/public. Recreate it every
# boot: the target is a container path, so a stale link is worse than none.
if [ ! -L public/storage ]; then
    php artisan storage:link --quiet || true
fi

# ---------------------------------------------------------------- #
# Caches                                                           #
# ---------------------------------------------------------------- #
# Built at boot, not at image-build time, so the cached config reflects the
# environment variables this container was actually started with.
php artisan config:clear --quiet
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet

# The commands above ran as root, so hand the files they just wrote back to
# the PHP-FPM user — it still needs to write logs, sessions and stray views.
chown -R www-data:www-data storage bootstrap/cache

# ---------------------------------------------------------------- #
# Database                                                         #
# ---------------------------------------------------------------- #
# Off by default so a redeploy never touches the schema unexpectedly; set
# RUN_MIGRATIONS=true on the Render service to migrate on every boot.
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force --isolated || php artisan migrate --force
fi

# Seeds the demo catalogue once. Guard it with a one-shot flag rather than
# leaving it on, or every restart re-runs the seeders.
if [ "${RUN_SEEDERS}" = "true" ]; then
    echo "Running seeders..."
    php artisan db:seed --force
fi

exec "$@"
