# OpsEvidence — Modelo de Datos

> Estado: Fase 0/1. Versión 1.0 — 2026-09-10. Motor: PostgreSQL 16.

## 1. Convenciones

- Claves primarias `bigint` autoincrementales (`bigserial`). No se usan UUID como PK: encarecen los
  índices y complican el depurado, sin aportar valor en una BD única.
- Timestamps con zona horaria (`timestamptz`). Todo se almacena en UTC.
- `metadata` / `data` / `raw_data` / `configuration` en **JSONB** con índice GIN **solo donde se
  consulta**; indexar todo JSONB sale caro y no se usa.
- Enums como `varchar` + `CHECK` en lugar de tipos `ENUM` nativos de PostgreSQL: añadir un valor a un
  ENUM nativo requiere DDL bloqueante; con `CHECK` es una migración trivial.
- `account_id` desnormalizado en toda tabla de negocio (ver `ADR-002`).
- Borrado lógico (`active` / `deleted_at`) en `clients` y `assets`; el resto se borra en cascada.

## 2. Diagrama de relaciones

```
accounts ──┬── users
           ├── api_tokens
           ├── rule_settings
           ├── audit_logs
           └── clients ──┬── environments ──┐
                         ├── assets ◄───────┘
                         ├── integrations
                         ├── incidents ──── evidence
                         ├── activities
                         ├── reports
                         └── daily_summaries
                                ▲
                           assets ── checks ── check_runs ── evidence
```

## 3. Revisión crítica del modelo propuesto

El modelo de partida era correcto en su forma. Estos son los cambios aplicados y el porqué:

| # | Cambio | Motivo |
|---|---|---|
| 1 | `evidence.value` (único) → `value_text` + `value_numeric` + `unit` | El rules engine compara umbrales numéricos (disco > 90 %). Un `value` textual obliga a castear en cada regla y pierde precisión. |
| 2 | `evidence` + `raw_data` JSONB | Requisito explícito: la normalización no debe destruir el dato original. Permite reprocesar reglas sin volver a recolectar. |
| 3 | `evidence` pasa a **append-only** | Los snapshots históricos son el producto (ver `ADR-003`). |
| 4 | `evidence.client_id` desnormalizado | El dashboard y el informe filtran por cliente y rango de fecha: es la consulta más caliente del sistema. Evita un JOIN de tres niveles. |
| 5 | `checks` gana `interval_seconds`, `freshness_ttl_seconds`, `next_run_at`, `last_success_at`, `last_error`, `consecutive_failures` | Sin esto no se puede distinguir `stale` de `fresh`, ni evitar repetir checks fallidos, ni mostrar "sin datos". Es la pieza que hace honesto al producto. |
| 6 | `check_runs.error_message` + `status` con `failed`/`partial` | Un collector que falla en silencio es el peor fallo posible. |
| 7 | `incidents` con `signature` único entre incidentes abiertos | Evita que una regla crítica que se repite cada 5 minutos genere 288 incidentes al día. |
| 8 | Nueva tabla `api_tokens` (hash, scopes) | Requisito de seguridad del agente: tokens hasheados, revocables, con alcance acotado. Sanctum solo no cubre el scoping por cliente/asset. |
| 9 | Nueva tabla `rule_settings` | Los umbrales y el score deben ser configurables por cuenta (y opcionalmente por cliente). |
| 10 | Nueva tabla `daily_summaries` | El informe mensual no puede escanear millones de filas de evidencia cruda. Agregado diario calculado por job. |
| 11 | Nueva tabla `audit_logs` | Auditabilidad de acciones sensibles (crear/borrar clientes, ver informes, emitir tokens). |
| 12 | `reports` gana `health_score`, `snapshot`, `status`, `sent_at` | El informe es un documento **congelado** en un instante: debe reproducirse idéntico meses después aunque los datos cambien. |
| 13 | `integrations.credentials` cifrado + `credentials_reference` | Nunca texto plano. `credentials_reference` permite en el futuro delegar en un gestor externo sin migrar datos. |
| 14 | Índices compuestos orientados a las consultas reales | Ver §7. |
| 15 | `users.email` único global | Simplifica el login (un email → una cuenta). Con email único por cuenta, el login necesitaría resolver la cuenta primero. Limitación aceptada y documentada. |

## 4. Tablas

### 4.1 `accounts` — el tenant

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| name | varchar(150) | |
| slug | varchar(80) UNIQUE | |
| plan | varchar(20) | `freelancer`/`msp`/`msp_pro`; CHECK |
| status | varchar(20) | `active`/`suspended`; default `active` |
| client_limit | int | Límite del plan, nullable = ilimitado |
| settings | jsonb | Configuración de cuenta (marca, zona horaria, informe) |
| trial_ends_at | timestamptz NULL | Preparado para billing futuro |
| created_at / updated_at | timestamptz | |

### 4.2 `users`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id | bigint FK → accounts, ON DELETE CASCADE | |
| name | varchar(120) | |
| email | varchar(190) UNIQUE | Único global (ver §3.15) |
| password | varchar(255) | Hash bcrypt |
| role | varchar(20) | `owner`/`admin`/`technician`/`viewer`; CHECK |
| is_active | boolean default true | |
| last_login_at | timestamptz NULL | |
| created_at / updated_at / deleted_at | | |

Índice: `(account_id)`, `UNIQUE(email)`.

### 4.3 `clients`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id | bigint FK | |
| name | varchar(150) | |
| slug | varchar(80) | |
| description | text NULL | |
| contact_name / contact_email | varchar NULL | Para el encabezado del informe |
| active | boolean default true | |
| created_at / updated_at / deleted_at | | |

Constraints: `UNIQUE(account_id, slug)`. Índice `(account_id, active)`.

### 4.4 `environments`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id | bigint FK | Desnormalizado |
| client_id | bigint FK, CASCADE | |
| name | varchar(80) | `production`, `staging`, `development` |
| type | varchar(20) | `production`/`staging`/`development`/`other`; CHECK |
| created_at / updated_at | | |

Constraints: `UNIQUE(client_id, name)`.

### 4.5 `assets`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id / client_id / environment_id | FK | `environment_id` nullable |
| name | varchar(150) | `web-server-01` |
| type | varchar(30) | Ver §5; CHECK |
| hostname | varchar(255) NULL | |
| address | varchar(255) NULL | IP o URL |
| provider | varchar(80) NULL | Hetzner, DO… informativo |
| metadata | jsonb NULL | |
| active | boolean default true | |
| last_evidence_at | timestamptz NULL | Desnormalizado para listados rápidos |
| created_at / updated_at / deleted_at | | |

Constraints: `UNIQUE(client_id, name)`. Índices: `(account_id, client_id)`, `(client_id, type)`.

### 4.6 `integrations`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id / client_id | FK | |
| type | varchar(30) | `github`/`docker`/`uptime_kuma`/`backup_webhook`/… |
| name | varchar(120) | |
| configuration | jsonb | **No secreto**: URLs, org, repo, intervalos |
| credentials | text NULL | Cifrado con `APP_KEY` (cast `encrypted:array`) |
| credentials_reference | varchar(255) NULL | Reservado para gestor externo futuro |
| active | boolean default true | |
| last_sync_at / last_success_at | timestamptz NULL | Observabilidad |
| last_error | text NULL | |
| created_at / updated_at | | |

Constraints: `UNIQUE(client_id, type, name)`.

### 4.7 `checks`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id / client_id / asset_id | FK | |
| type | varchar(40) | Ver §6; CHECK |
| name | varchar(150) | |
| configuration | jsonb | Umbrales específicos del check |
| interval_seconds | int default 600 | Frecuencia |
| freshness_ttl_seconds | int default 1800 | A partir de aquí los datos son `stale` |
| enabled | boolean default true | |
| last_run_at / last_success_at | timestamptz NULL | |
| last_status | varchar(20) NULL | Estado normalizado del último run |
| last_error | text NULL | |
| consecutive_failures | int default 0 | Protección contra reintentos infinitos |
| next_run_at | timestamptz NULL | Planificación |
| created_at / updated_at | | |

Constraints: `UNIQUE(asset_id, type, name)`. Índices: `(enabled, next_run_at)`, `(account_id, client_id)`.

### 4.8 `check_runs`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id / check_id | FK | |
| status | varchar(20) | `success`/`failed`/`partial`/`skipped`; CHECK |
| started_at / finished_at | timestamptz | |
| duration_ms | int NULL | |
| error_message | text NULL | |
| metadata | jsonb NULL | Trace, correlation id, contadores |

Índices: `(check_id, started_at DESC)`, `(account_id, started_at DESC)`.
Retención: 90 días (configurable) — vía comando programable, no en el MVP.

### 4.9 `evidence` (append-only)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id / client_id / asset_id | FK | Desnormalizados |
| check_id | FK NULL | NULL para evidencia manual o por webhook |
| check_run_id | FK NULL | |
| type | varchar(40) | Igual que los tipos de check |
| status | varchar(20) | **Normalizado**: `HEALTHY`/`WARNING`/`CRITICAL`/`UNKNOWN`/`FAILED` |
| raw_status | varchar(120) NULL | Estado tal como lo entregó la fuente |
| title | varchar(255) | Texto legible |
| value_text | varchar(255) NULL | |
| value_numeric | numeric(20,4) NULL | Para reglas y gráficos |
| unit | varchar(30) NULL | `%`, `ms`, `bytes`, `days` |
| data | jsonb NULL | Payload normalizado |
| raw_data | jsonb NULL | Payload original sin tocar |
| source | varchar(40) | `check`/`agent`/`webhook`/`manual` |
| collected_at | timestamptz | Momento real de la observación |
| created_at | timestamptz | Momento de ingesta |

Índices: `(account_id, client_id, collected_at DESC)` — dashboard e informe;
`(asset_id, type, collected_at DESC)` — detalle de asset;
`(check_id, collected_at DESC)` — series temporales;
`(client_id, status, collected_at DESC)` — riesgos abiertos.

Sin `updated_at`: las filas no se modifican nunca.

### 4.10 `incidents`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id / client_id / asset_id | FK | `asset_id` nullable |
| rule_key | varchar(60) | `SSL_EXPIRING`, `DISK_USAGE`… |
| signature | varchar(190) | `rule_key:asset_id` — deduplicación |
| severity | varchar(20) | `info`/`warning`/`critical`; CHECK |
| status | varchar(20) | `open`/`acknowledged`/`resolved`/`ignored`; CHECK |
| title / description | varchar | |
| evidence_id | FK NULL | Evidencia que lo disparó |
| opened_at | timestamptz | |
| acknowledged_at / acknowledged_by | timestamptz / FK users NULL | |
| resolved_at / resolved_by | | |
| resolution_note | text NULL | |
| metadata | jsonb NULL | |

Índices: `(account_id, status, opened_at DESC)`, `(client_id, status)`;
**`UNIQUE(client_id, signature) WHERE status IN ('open','acknowledged')`** — índice parcial:
un solo incidente vivo por regla y asset.

### 4.11 `activities`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id / client_id | FK | |
| asset_id | FK NULL | |
| user_id | FK users NULL | Autor |
| type | varchar(30) | `maintenance`/`upgrade`/`ssl_renewal`/`restore`/`deploy`/`config_change`/`restart`/`other` |
| title / description | varchar | |
| performed_at | timestamptz | |
| billable | boolean default false | Futuro: horas facturables |
| metadata | jsonb NULL | |
| created_at / updated_at | | |

Índice: `(client_id, performed_at DESC)`.

### 4.12 `reports`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id / client_id | FK | |
| period_start / period_end | date | Mes calendario |
| status | varchar(20) | `draft`/`generating`/`ready`/`sent`; CHECK |
| health_score | int NULL | Congelado al generar |
| summary | jsonb NULL | Titulares del informe |
| metrics | jsonb NULL | Métricas agregadas |
| snapshot | jsonb NULL | Copia congelada de los datos usados |
| sent_at | timestamptz NULL | Instrumentación de la hipótesis de negocio |
| generated_by | FK users NULL | |
| created_at / updated_at | | |

Constraints: `UNIQUE(client_id, period_start, period_end)` — un informe por mes y cliente.
Índice: `(account_id, status, period_end DESC)`.

### 4.13 `daily_summaries`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id / client_id | FK | |
| date | date | |
| metrics | jsonb | `{checks_total, healthy, warning, critical, failures, backups_ok, backups_failed, avg_response_ms, availability_pct, …}` |
| computed_at | timestamptz | |

Constraints: `UNIQUE(client_id, date)`. Índice `(client_id, date DESC)`.
Se conserva indefinidamente: es la base del histórico del informe.

### 4.14 `rule_settings`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id | FK | |
| client_id | FK NULL | NULL = valor por defecto de la cuenta |
| rule_key | varchar(60) | |
| enabled | boolean default true | |
| thresholds | jsonb | `{"warning": 80, "critical": 90}` |
| severity | varchar(20) NULL | Sobrescribe la severidad por defecto |
| created_at / updated_at | | |

Índices únicos parciales (PostgreSQL trata `NULL` como distinto, por eso se requieren dos):
`UNIQUE(account_id, rule_key) WHERE client_id IS NULL` y
`UNIQUE(account_id, client_id, rule_key) WHERE client_id IS NOT NULL`.

### 4.15 `api_tokens` — tokens de agente e integración

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id / client_id | FK | `client_id` NULL = token de cuenta |
| asset_id | FK NULL | Alcance fino |
| name | varchar(120) | |
| token_hash | char(64) UNIQUE | SHA-256 del token. **El token en claro no se almacena ni se registra.** |
| prefix | varchar(12) | Primeros caracteres, para mostrarlos en la UI |
| abilities | jsonb | `["evidence:write"]` |
| last_used_at / last_used_ip | | |
| expires_at | timestamptz NULL | |
| revoked_at | timestamptz NULL | Revocación inmediata sin borrar auditoría |
| created_by | FK users NULL | |
| created_at / updated_at | | |

### 4.16 `audit_logs`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigserial PK | |
| account_id | FK | |
| user_id | FK NULL | NULL si vino de token de agente |
| event | varchar(60) | `client.created`, `token.revoked`, `report.sent`… |
| auditable_type / auditable_id | varchar / bigint NULL | |
| changes | jsonb NULL | Valores anteriores/nuevos relevantes |
| ip_address | inet NULL | |
| created_at | timestamptz | |

Índices: `(account_id, created_at DESC)`, `(auditable_type, auditable_id)`.

## 5. Tipos de asset

`SERVER`, `WEBSITE`, `APPLICATION`, `DATABASE`, `CONTAINER_HOST`, `REPOSITORY`,
`BACKUP_SOURCE`, `OTHER`.

Extensible: se validan con `CHECK` y una constante de dominio en PHP
(`App\Domain\AssetType`), de modo que añadir un tipo sea un cambio de una línea más una migración.

## 6. Tipos de check

`HTTP_STATUS`, `HTTP_RESPONSE_TIME`, `SSL_EXPIRATION`, `SERVER_UPTIME`, `CPU_USAGE`,
`MEMORY_USAGE`, `DISK_USAGE`, `PENDING_UPDATES`, `DOCKER_CONTAINER_STATUS`, `DOCKER_HEALTH`,
`BACKUP_STATUS`, `GITHUB_WORKFLOW`.

Cada tipo es una clase que implementa `CheckDefinition` y declara: nombre, tipos de asset
compatibles, esquema de configuración, valores por defecto y la regla de normalización
correspondiente. Añadir un check = añadir una clase, sin tocar el resto del sistema.

## 7. Índices: justificación por consulta

| Consulta | Índice |
|---|---|
| Dashboard general de una cuenta | `evidence(account_id, client_id, collected_at DESC)` |
| Informe de un cliente y mes | `daily_summaries(client_id, date DESC)` |
| Detalle de un asset | `evidence(asset_id, type, collected_at DESC)` |
| Serie temporal de un check | `evidence(check_id, collected_at DESC)` |
| Riesgos abiertos de un cliente | `evidence(client_id, status, collected_at DESC)` |
| Checks a ejecutar | `checks(enabled, next_run_at)` |
| Incidentes vivos | `incidents(client_id, status, opened_at DESC)` + índice parcial único |

## 8. Escalabilidad y retención

- **Volumen:** se estiman ~4,3 M filas de `evidence`/mes en el techo del MVP. Con los índices
  anteriores PostgreSQL los maneja sin particionar.
- **Disparador de particionado:** si `evidence` supera ~100 M de filas o las consultas del informe
  pasan de 1 s, se particiona por rango de `collected_at` (mensual). El diseño append-only lo permite
  sin cambios de esquema. Documentado como deuda técnica consciente, no se implementa ahora.
- **Retención:** evidencia cruda 90 días por defecto (configurable por cuenta); `daily_summaries`,
  `incidents`, `reports` y `activities` se conservan indefinidamente. Comando
  `opsevidence:prune-evidence`, programable y con `--dry-run`.

## 9. Auditabilidad

- `evidence` es inmutable: nunca se actualiza ni se borra (salvo retención programada).
- `audit_logs` registra las acciones sensibles con actor, IP y cambios.
- Toda entidad lleva `created_at`; `incidents` y `reports` llevan el ciclo de vida completo.
- `reports.snapshot` permite reproducir exactamente lo que se envió al cliente.

## 10. Lo que deliberadamente NO se modela

- Tablas de series temporales dedicadas (Prometheus, TimescaleDB): sobredimensionado para el MVP.
- Multi-moneda, impuestos o suscripciones: billing está fuera de alcance.
- Permisos granulares por recurso: 4 roles fijos bastan (ver `docs/security.md` §RBAC).
- Versionado de assets: si un asset cambia de hostname se actualiza la fila; el histórico relevante
  vive en la evidencia, no en el asset.
