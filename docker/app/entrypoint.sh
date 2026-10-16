#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
    if [ "${APP_SOURCE_MOUNTED:-false}" = "true" ]; then
        chown "$(stat -c '%u:%g' .)" .env
    fi
fi

if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force
php artisan db:seed --force

if [ "${APP_SOURCE_MOUNTED:-false}" = "true" ]; then
    chmod -R a+rwX storage bootstrap/cache
else
    chown -R www-data:www-data storage bootstrap/cache
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
