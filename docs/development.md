# Desarrollo local

## Requisitos

- Docker Desktop (o Docker Engine + Compose).
- `node` y `python` para trabajar fuera de contenedores (opcional).

`make` no es obligatorio: en Windows puedes ejecutar los comandos equivalentes de
`docker compose` directamente. El `Makefile` cubre el flujo en Linux/CI.

## Arranque

```bash
cp .env.example .env
docker compose up -d --build --wait
```

El servicio `init` (oneshot) se encarga de: `composer install`, generar `APP_KEY`,
ejecutar migraciones y sembrar la demo. Por eso el primer arranque tarda un poco.

## Comandos útiles

```bash
docker compose logs -f              # logs de todo
docker compose logs -f worker       # logs del worker de colas
docker compose exec backend php artisan tinker
docker compose exec postgres psql -U opsevidence -d opsevidence
docker compose down -v              # DESTRUCTIVO: borra la base de datos
```

## Backend

```bash
docker compose exec backend php artisan test
docker compose exec backend vendor/bin/pint          # formatea
docker compose exec backend vendor/bin/pint --test   # verifica
docker compose exec backend vendor/bin/phpstan analyse
```

Los tests corren contra `opsevidence_test` (PostgreSQL). El `phpunit.xml` fija la
configuración de testing; no uses SQLite: el esquema usa JSONB e índices parciales.

## Frontend

```bash
docker compose exec frontend npm run lint
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run test
docker compose exec frontend npm run build
```

`node_modules` vive en un volumen de Docker (no en el repo), igual que `vendor`.

## Agente

```bash
cd agent
python -m venv .venv
.venv/bin/python -m pip install -e ".[dev]"
.venv/bin/python -m pytest -q
.venv/bin/python -m ruff check .
```

## Convenciones

- La lógica vive en `app/Services/*`, no en los controllers.
- Una feature que no "recopila, normaliza, demuestra o reporta evidencia" va al
  backlog, no al código.
- Toda entidad de negocio lleva `account_id` y usa el trait `BelongsToAccount`.
- La evidencia es append-only: no existe endpoint de update/delete.

## PHPStan

`phpstan.neon` incluye un baseline (`phpstan-baseline.neon`) con errores conocidos.
Al introducir código nuevo que PHPStan detecte, no regeneres el baseline a ciegas:
corrige el error o justifica la excepción.
