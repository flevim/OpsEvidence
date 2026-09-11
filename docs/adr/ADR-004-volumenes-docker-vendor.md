# ADR-004 — Volúmenes Docker para `vendor`, `node_modules`, `storage` y cache

- **Fecha:** 2026-09-10
- **Estado:** Aceptada

## Contexto

El repositorio vive dentro de OneDrive. `vendor/` y `node_modules/` suman decenas de
miles de archivos: sincronizarlos degrada el rendimiento y arriesga bloqueos de
archivos en Windows.

## Decisión

Los directorios de alto churn que no forman parte del código fuente se montan como
**volúmenes nombrados de Docker**, no como bind mounts:

- `backend/vendor`
- `backend/storage`
- `backend/bootstrap/cache`
- `frontend/node_modules`

El código fuente (PHP, Vue, migraciones, tests) sigue en bind mount para edición en vivo.

## Consecuencias

- OneDrive solo sincroniza código real, no artefactos de build.
- `vendor` y `node_modules` no se ven desde el host: los comandos se ejecutan dentro
  de contenedores (`docker compose exec`).
- El primer arranque necesita `composer install` / `npm install`, que ejecuta el
  servicio `init` y el contenedor `frontend`.

## Alternativas descartadas

- **Bind mount de todo:** simple, pero insostenible con OneDrive.
- **Volúmenes para todo el código:** perdería la edición en vivo.
