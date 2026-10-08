#!/bin/sh
set -e

if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link
fi

exec "$@"
