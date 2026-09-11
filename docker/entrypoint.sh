#!/bin/sh
set -eu

mkdir -p \
    database \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

touch database/database.sqlite
php artisan package:discover --ansi
php artisan migrate --force --no-interaction

exec "$@"
