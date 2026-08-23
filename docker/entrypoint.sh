#!/bin/sh

set -eu

mkdir -p storage/app/public
chown www-data:www-data storage/app/public
php artisan storage:link
php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
