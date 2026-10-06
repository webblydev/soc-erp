#!/bin/sh
set -e

cd /var/www/html

# A mounted storage volume starts empty; recreate the tree Laravel expects.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/logs storage/app/public storage/app/private bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

php artisan optimize
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
