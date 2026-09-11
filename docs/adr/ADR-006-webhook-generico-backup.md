# ADR-006 — Webhook genérico como estrategia de integración de backups

- **Fecha:** 2026-09-11
- **Estado:** Aceptada

## Contexto

Hay docenas de herramientas de backup (restic, borg, Veeam, pg_dump, …). Construir
una integración nativa por cada una es deuda de mantenimiento infinita para un solo
desarrollador.

## Decisión

Exponer **una única interfaz** para resultados de backup:

```
POST /api/webhooks/backup/{token}
{"source": "postgres-production", "status": "success", "size": 1258291200, "duration_seconds": 83}
```

El token es opaco, se guarda hasheado y se revoca en el panel. Cualquier script
existente queda integrado con un `curl` al final del backup.

## Consecuencias

- Cobertura universal con un solo endpoint y un solo modelo de evidencia
  (`BACKUP_STATUS`).
- La carga de la integración se traslada al script del cliente, que ya existe.
- Menos superficies de autenticación que N SDKs.

## Alternativas descartadas

- **Integraciones nativas por producto (20+):** alto costo de mantenimiento, bajo
  valor marginal en el MVP.
- **Recolectar backups desde el agente:** el backup corre en muchos sitios, no solo
  en el servidor del agente.
