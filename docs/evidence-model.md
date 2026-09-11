# OpsEvidence — Modelo de Evidencia

> Estado: Fase 0. Versión 1.0 — 2026-09-10.

## 1. Qué es una evidencia

Una **evidencia** es un hecho técnico observado en un instante, atribuible a un activo, con su
resultado y su procedencia, almacenado de forma permanente y no modificable.

La pregunta que toda evidencia debe poder responder:

> **¿Qué** ocurrió, **dónde**, **cuándo**, **quién o qué** lo recopiló, **con qué resultado** y
> **con qué dato original**.

## 2. El sobre (envelope) de evidencia

Toda fuente —coleector HTTP, agente Linux, webhook de backup— produce la misma estructura:

```json
{
  "type": "BACKUP_STATUS",
  "asset": "postgres-production",
  "status": "HEALTHY",
  "raw_status": "success",
  "title": "Backup de PostgreSQL completado",
  "value_text": "success",
  "value_numeric": 1258291200,
  "unit": "bytes",
  "source": "webhook",
  "collected_at": "2026-09-10T03:01:43Z",
  "data": {
    "duration_seconds": 83,
    "size_bytes": 1258291200
  },
  "raw_data": {
    "source": "postgres-production",
    "status": "success",
    "size": 1258291200,
    "duration_seconds": 83
  }
}
```

### Campos

| Campo | Obligatorio | Descripción |
|---|---|---|
| `type` | Sí | Tipo de evidencia (coincide con el tipo de check cuando aplica). |
| `asset` | Sí | Nombre o id del activo. Se resuelve dentro del alcance del token/cliente. |
| `status` | Sí | Estado **normalizado** (§3). |
| `raw_status` | No | Estado tal como lo reportó la fuente. **Se conserva siempre.** |
| `title` | Sí | Texto legible por un humano, en el idioma del informe. |
| `value_text` | No | Valor cualitativo (`success`, `up`). |
| `value_numeric` | No | Valor numérico para reglas y gráficos. |
| `unit` | No | `%`, `ms`, `bytes`, `days`, `count`. |
| `data` | No | Payload normalizado, estable y documentado. |
| `raw_data` | No | Payload original **sin transformar**. |
| `source` | Sí | `check` \| `agent` \| `webhook` \| `manual`. |
| `collected_at` | Sí | Momento de la observación (no el de la ingesta). |

## 3. Estados normalizados

Cinco estados canónicos, únicos en todo el sistema:

| Estado | Significado | Uso en la UI |
|---|---|---|
| `HEALTHY` | Todo correcto | Texto "Saludable" + icono ✓ |
| `WARNING` | Degradado, requiere atención | "Atención" + icono ⚠ |
| `CRITICAL` | Falla o riesgo inmediato | "Crítico" + icono ✕ |
| `FAILED` | La recolección en sí falló | "Fallo de recolección" + icono ⟳ |
| `UNKNOWN` | No se puede determinar | "Desconocido" + icono ? |

**Accesibilidad:** el estado nunca se comunica solo con color. Siempre color + texto + icono
(ver `docs/product.md` §42).

### Diferencia entre `CRITICAL` y `FAILED`

Es una distinción deliberada y central:

- `CRITICAL` → **el activo está mal** (disco al 95 %, servicio caído, backup falló).
- `FAILED` → **no pudimos obtener el dato** (timeout, credencial inválida, API cambió).

Confundirlos produce reportes deshonestos: un timeout se vería como "servicio caído". Los
incidentes generados por `FAILED` son de tipo *problema de recolección* y se tratan aparte.

## 4. Capa de normalización

### 4.1 Responsabilidad

Traducir representaciones heterogéneas a los cinco estados canónicos, **sin destruir el original**.
Vive en `app/Services/Normalization/` y se implementa como mapeadores por tipo de evidencia.

### 4.2 Tabla de mapeo

| Fuente | Dato original | Normalizado | Nota |
|---|---|---|---|
| Uptime Kuma | `status = UP` | `HEALTHY` | |
| Uptime Kuma | `status = PENDING` | `UNKNOWN` | Aún sin datos |
| Uptime Kuma | `status = DOWN` | `CRITICAL` | |
| Docker | `State.Running = true` y health `healthy` | `HEALTHY` | |
| Docker | `State.Running = true` y health `starting` | `WARNING` | |
| Docker | `State.Running = false` con restart policy | `WARNING` | Reinicio esperado |
| Docker | `State.Running = false` sin política | `CRITICAL` | |
| Docker | `State.Health = unhealthy` | `CRITICAL` | |
| HTTP | `status_code` 200–399 | `HEALTHY` | |
| HTTP | `status_code` 400–499 | `WARNING` | |
| HTTP | `status_code` ≥ 500 | `CRITICAL` | |
| HTTP | timeout / sin conexión | `CRITICAL` | El activo no responde |
| SSL | días restantes > 30 | `HEALTHY` | |
| SSL | días restantes 7–30 | `WARNING` | |
| SSL | días restantes < 7 o expirado | `CRITICAL` | |
| SSL | error de handshake | `FAILED` | No se pudo medir |
| Disco | uso < 80 % | `HEALTHY` | |
| Disco | uso 80–90 % | `WARNING` | |
| Disco | uso > 90 % | `CRITICAL` | |
| Backup | `status = success` | `HEALTHY` | |
| Backup | `status = success` con warnings | `WARNING` | |
| Backup | `status = failed` | `CRITICAL` | |
| Backup | sin backup dentro de la ventana | `CRITICAL` | Regla `BACKUP_STALE` |
| Actualizaciones | 0 de seguridad | `HEALTHY` | |
| Actualizaciones | ≥ 1 de seguridad | `WARNING` | |
| GitHub Actions | `conclusion = success` | `HEALTHY` | |
| GitHub Actions | `conclusion = failure` | `CRITICAL` | |
| GitHub Actions | `conclusion = cancelled` | `WARNING` | |
| GitHub Actions | `status = in_progress` | `UNKNOWN` | Aún no hay resultado |
| Cualquiera | excepción de recolección | `FAILED` | Con `error_message` en el `check_run` |

### 4.3 Regla de oro

> La normalización es una **función pura y probable**: mismos datos de entrada, mismo estado de
> salida. No consulta la base de datos ni la red. Esto la vuelve trivialmente testeable.

## 5. Frescura (freshness)

Un estado sin marca temporal es incompleto. Todo dato mostrado lleva su frescura:

| Valor | Condición | Presentación |
|---|---|---|
| `fresh` | `now - last_success_at ≤ freshness_ttl_seconds` | Valor normal |
| `stale` | `now - last_success_at > freshness_ttl_seconds` | Valor + "Datos de hace X" |
| `never_collected` | Nunca hubo ejecución exitosa | **"Sin datos" — nunca un cero** |

**Reglas de presentación obligatorias:**

- En agregados (disponibilidad, backups), los checks `never_collected` se **excluyen del
  denominador** y se reportan aparte como "N servicios sin datos".
- Si **todos** los checks de un cliente están sin datos, el informe lo dice explícitamente y no
  muestra un score de 0.

## 6. Snapshots e histórico

OpsEvidence debe poder responder: *¿cómo estaba la infraestructura ayer? ¿y hace un mes?*

### Estrategia

1. **Evidencia cruda inmutable.** Cada observación es una fila nueva. Nunca `UPDATE`. Responde a
   "¿qué pasó exactamente y cuándo?".
2. **Agregado diario** (`daily_summaries`). Job nocturno que condensa el día en una fila de métricas
   por cliente. Responde a "¿cómo evolucionó?" con costo de lectura mínimo.
3. **Congelado del informe** (`reports.snapshot`). Al generar un informe, los datos usados se copian
   dentro del propio informe. Responde a "¿qué le enviamos al cliente el mes pasado?" aunque los
   datos crudos hayan sido purgados por retención.

Tres granularidades, tres propósitos, sin duplicación innecesaria.

### Retención

| Dato | Retención | Justificación |
|---|---|---|
| `evidence` cruda | 90 días (configurable) | El detalle fino pierde valor rápido; el histórico se conserva agregado. |
| `check_runs` | 90 días | Igual. |
| `daily_summaries` | Indefinida | Base del histórico y de los informes. |
| `reports` + `snapshot` | Indefinida | Es el entregable legal/comercial del cliente. |
| `incidents` | Indefinida | Trazabilidad de lo ocurrido. |
| `activities` | Indefinida | Justifica el trabajo facturado. |
| `audit_logs` | 1 año | Auditoría. |

Implementación: comando `opsevidence:prune-evidence` con `--dry-run` y corte configurable. No se
construye archivado en frío ni almacenamiento externo en el MVP.

## 7. Idempotencia

Un mismo hecho no debe duplicarse por reintentos del collector.

- Clave lógica: `(check_id, type, date_trunc('minute', collected_at))`.
- El ingest usa `INSERT ... ON CONFLICT DO NOTHING` sobre un índice único parcial que cubre solo la
  evidencia originada por checks.
- Los webhooks aceptan un `Idempotency-Key` opcional; si se repite, la segunda llamada responde
  `200` con el registro existente en lugar de crear otro.

Esto importa porque un `queue:work` reiniciado a mitad de lote reprocesa jobs: sin idempotencia,
duplicaríamos cada fila de evidencia.

## 8. Contrato de ingesta

Un único punto de entrada interno (`EvidenceIngestor`) recibe envíos de **todas** las fuentes:

```
Collectors ─┐
Agent     ──┼──► EvidenceIngestor ──► Normalization ──► evidence (append-only)
Webhooks  ──┤                          │
Manual    ──┘                          └──► Rules engine ──► incidents
```

Ventajas: una sola validación, una sola normalización, un solo punto de auditoría y un solo lugar
donde aplicar idempotencia. Si mañana entra una fuente nueva, no se toca el resto del sistema.

## 9. Errores en la recolección

Nunca se descarta un fallo en silencio:

1. El `check_run` se cierra con `status = failed` y `error_message`.
2. Se incrementa `checks.consecutive_failures` y se actualiza `checks.last_error`.
3. Se emite evidencia con `status = FAILED` **solo si** había un valor previo esperado; si no,
   el check queda en `never_collected`.
4. A partir del tercer fallo consecutivo se genera un incidente `COLLECTOR_FAILING`
   (warning), para que el técnico sepa que **el problema es nuestro, no del cliente**.

Este último punto evita el peor escenario posible de confianza: un informe vacío sin explicación.
