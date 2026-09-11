#!/bin/sh
set -e

cd /var/www/html

ensure_storage() {
    mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

    chmod -R 0777 storage bootstrap/cache 2>/dev/null || true
}

echo "[init] Preparando backend de OpsEvidence..."

ensure_storage

if [ ! -f vendor/autoload.php ]; then
    echo "[init] Instalando dependencias de Composer (vendor/ vacio)..."
    composer install --no-interaction --prefer-dist --no-progress
else
    echo "[init] vendor/ ya presente, se omite composer install."
fi

if [ ! -f .env ]; then
    echo "[init] Creando .env a partir de .env.example..."
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "[init] Generando APP_KEY..."
    php artisan key:generate --force --no-interaction
fi

echo "[init] Esperando a PostgreSQL y ejecutando migraciones..."
attempt=0
until php artisan migrate --force --no-interaction; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "[init] ERROR: las migraciones fallaron tras $attempt intentos." >&2
        exit 1
    fi
    sleep 2
done

if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    echo "[init] Sembrando datos de demostracion (idempotente)..."
    php artisan db:seed --force --no-interaction
fi

php artisan storage:link >/dev/null 2>&1 || true

echo "[init] Listo."
