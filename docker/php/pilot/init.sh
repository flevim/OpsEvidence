#!/bin/sh
set -eu

cd /var/www/html

case "${APP_KEY:-}" in
    base64:*) ;;
    *)
        echo "[init] ERROR: APP_KEY debe contener una clave base64 valida." >&2
        exit 1
        ;;
esac

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

echo "[init] Aplicando migraciones..."
php artisan migrate --force --no-interaction

if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    echo "[init] Cargando datos de demostracion por solicitud explicita..."
    php artisan db:seed --force --no-interaction
fi

php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[init] Piloto preparado."
