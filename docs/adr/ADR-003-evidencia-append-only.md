# ADR-003 — Evidencia append-only con `raw_data` en JSONB

- **Fecha:** 2026-09-10
- **Estado:** Aceptada

## Contexto

El producto debe responder "¿cómo estaba la infraestructura ayer? ¿y hace un mes?".
Sobrescribir evidencia destruiría el histórico y la auditabilidad.

## Decisión

- `evidence` es **inmutable**: cada observación es una fila nueva; no existe
  `updated_at` ni endpoint de update/delete.
- El dato original se conserva en `raw_data` (JSONB) junto al dato normalizado.
- Idempotencia por `dedup_key` (hash de check + tipo + minuto) con índice único:
  un reintento de cola no duplica filas.
- El histórico de largo plazo se conserva **agregado** en `daily_summaries` y
  **congelado** en `reports.snapshot`; la evidencia cruda tiene retención configurable.

## Consecuencias

- Normalizar no destruye el original: las reglas se pueden reprocesar sin re-colectar.
- `INSERT ... ON CONFLICT (dedup_key) DO NOTHING` hace la ingesta segura ante workers
  concurrentes.
- Se paga volumen de escritura; se mitiga con agregados + retención.

## Alternativas descartadas

- **Sobrescribir estado actual:** más barato en disco, pero sin histórico ni auditoría.
- **Event sourcing completo:** complejidad desproporcionada para el MVP.
