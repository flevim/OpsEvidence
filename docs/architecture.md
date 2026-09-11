# OpsEvidence — Arquitectura

> Estado: Fase 0/1. Versión 1.0 — 2026-09-10.

## 1. Principio arquitectónico

> OpsEvidence no construye monitorización. Construye una **capa de evidencia** sobre la
> monitorización que ya existe.

Antes de construir cualquier collector se evalúa, en este orden:

1. ¿Existe una **API** que exponga el dato? → consumirla.
2. ¿Existe un **webhook** o un script que el cliente ya ejecuta? → recibirlo.
3. ¿Existe un **archivo** o comando local estándar? → leerlo con un agente mínimo.
4. Solo entonces: escribir lógica propia.

## 2. Pipeline central

Todo el sistema es una tubería con cinco etapas. Toda feature debe situarse en una de ellas.

```
  COLLECT  →  NORMALIZE  →  STORE  →  ANALYZE  →  REPORT
     │            │           │          │           │
  collectors   mapping    PostgreSQL   rules      HTML/PDF
  agentes      a estados   histórico   incidents  informe
  webhooks     canónicos   append-only  health     dashboard
```

| Etapa | Responsabilidad | Dónde vive en el código |
|---|---|---|
| COLLECT | Obtener el dato crudo de la fuente | `app/Services/Collectors/*`, `agent/` |
| NORMALIZE | Traducir a estados y tipos canónicos | `app/Services/Normalization/*` |
| STORE | Persistir sin sobrescribir | `evidence`, `check_runs` |
| ANALYZE | Derivar problemas y score | `app/Services/Rules/*`, `Health/*` |
| REPORT | Presentar al humano | `app/Services/Reporting/*`, SPA Vue |

## 3. Vista de componentes

```
┌──────────────────────────────────────────────────────────────────┐
│                    Navegador (SPA Vue 3)                         │
│      Vue 3 + TS + Vite + Pinia + Vue Router + Vuetify 3          │
└───────────────────────────┬──────────────────────────────────────┘
                            │ HTTPS / JSON (Sanctum token)
┌───────────────────────────▼──────────────────────────────────────┐
│                     Laravel REST API (monolito)                  │
│  ┌────────────┬───────────────┬──────────────┬────────────────┐  │
│  │ Controllers│  Policies     │  Services    │  Jobs / Queue  │  │
│  │ (REST)     │  (tenancy)    │  (dominio)   │  (async)       │  │
│  └────────────┴───────────────┴──────────────┴────────────────┘  │
└──────────────┬───────────────────────────────┬───────────────────┘
               │                               │
      ┌────────▼────────┐              ┌───────▼─────────┐
      │   PostgreSQL    │              │   Redis Queue   │
      │ (datos + JSONB) │              │  + Scheduler    │
      └─────────────────┘              └───────┬─────────┘
                                               │
                                       ┌───────▼─────────┐
                                       │ Queue Worker    │
                                       │ (collect jobs)  │
                                       └───────┬─────────┘
                                               │
        ┌──────────────┬───────────────┬───────┴────────┬──────────────┐
        ▼              ▼               ▼                ▼              ▼
   HTTP / SSL     Linux Agent      Docker          GitHub        Backup
   (jobs)         (Python CLI)   (vía agente)   (REST API)    (webhook REST)
```

## 4. Stack y justificación

| Capa | Tecnología | Por qué |
|---|---|---|
| Frontend | Vue 3 + TS + Vite + Pinia + Vuetify 3 | Stack solicitado; Vuetify acelera tablas/formularios B2B. |
| Backend | Laravel + Sanctum | Un solo lenguaje para API, colas, scheduler, tests y PDF/HTML futuro. |
| BD | PostgreSQL | JSONB para `raw_data` y `metadata` sin esquemas rígidos; suficiente para el MVP. |
| Cola | Redis + Laravel Queue | Estándar, simple, ya requerido por el scheduler. |
| Agente | Python 3 (stdlib + `requests`) | Cero instalación de toolchain en el servidor del cliente; `psutil` opcional. |
| Contenedores | Docker + Compose | Un comando de arranque; sin Kubernetes. |

**Descartado a propósito:** microservicios, Kubernetes, colas separadas por dominio, CQRS, event
sourcing, GraphQL. Ninguna de esas piezas resuelve un problema del MVP y todas elevan el costo de
mantenimiento de un solo desarrollador.

## 5. Multi-tenancy

**Estrategia: base de datos compartida, esquema compartido, discriminador `account_id`.**

- Toda entidad de negocio desciende de `accounts` y lleva `account_id` **desnormalizado** en la
  propia tabla (no solo accesible vía JOIN). Razón: permite filtrar y proteger con un único
  predicado y hace las policies triviales y auditables.
- La tabla `evidence` lleva además `client_id` desnormalizado para evitar JOINs de tres niveles en
  las consultas del dashboard y del informe, que son las más frecuentes.
- Se implementa un **global scope** en el modelo base (`BelongsToAccount`) que añade
  `where account_id = ?` automáticamente, más **policies** explícitas. Cinturón y tirantes.
- El scope global se desactiva de forma explícita en el código de mantenimiento (comandos artisan),
  nunca de forma implícita.

Justificación de no usar schema-por-tenant: con 5–25 clientes por MSP y decenas de MSP, una BD
compartida sobra; N esquemas implican N migraciones y un costo operativo desproporcionado.

Ver `ADR-002`.

## 6. Ejecución asíncrona

| Componente | Rol |
|---|---|
| `scheduler` | `php artisan schedule:work`. Dispara checks periódicos y sincronizaciones. |
| `worker` | `php artisan queue:work`. Ejecuta collectors, normalización, reglas y generación de informes. |

Diseño de jobs:

- Idempotentes: un check ejecutado dos veces no duplica evidencia (clave natural
  `check_id` + `collected_at` truncado al minuto).
- Con timeout y reintentos acotados (3 intentos, backoff exponencial).
- Aislados por tenant: el job recibe `check_id` y resuelve el resto, verificando `account_id`.
- Fallos registrados en `check_runs.status = failed` + `error_message`, además de la tabla `jobs`
  fallidos de Laravel.

**Backpressure:** los checks se agrupan por `account_id` en la cola para evitar que un tenant con
miles de checks concentre todos los workers. Un tenant ruidoso no degrada a los demás.

## 7. Frescura de datos (freshness)

Concepto transversal y no negociable (ver `docs/product.md` §5.4):

| Estado | Significado | Presentación |
|---|---|---|
| `fresh` | Datos dentro de la ventana de frescura del check | valor real |
| `stale` | Datos más antiguos que la ventana | valor + advertencia visible |
| `never_collected` | Nunca hubo ejecución exitosa | "Sin datos" — **nunca un cero** |

Se calcula a partir de `checks.last_success_at` y de `checks.freshness_ttl_seconds`.

## 8. Rendimiento y límites del MVP

Escala objetivo del MVP: **50 accounts × 25 clientes × ~20 assets × ~5 checks ≈ 125.000 checks**,
la mayoría con periodicidad de 5–15 minutos.

Estimación de escritura: si cada check corre cada 10 min → ~4,3 M filas de `evidence` al mes. Es
un volumen que PostgreSQL maneja sin dificultad con índices correctos, pero obliga a:

1. Índices por `(account_id, client_id, collected_at DESC)` para los listados.
2. **Agregados diarios** (`daily_summaries`) en vez de escanear evidencia cruda al construir el
   informe mensual. El informe lee agregados; el detalle es bajo demanda.
3. **Retención** configurable de evidencia cruda (por defecto 90 días), manteniendo agregados de
   forma indefinida. La política se documenta y se implementa como comando artesanal programable;
   no se construye un sistema de archivado en el MVP.

## 9. Riesgos técnicos y mitigación

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Explosión de volumen en `evidence` | Consultas lentas, disco | Índices + agregados diarios + retención configurable |
| Collectors frágiles ante cambios de API | Datos faltantes silenciosos | Estado `never_collected`/`stale` explícito; errores visibles en UI |
| Fuga de credenciales de integraciones | Brecha de seguridad | Cifrado Laravel + `credentials_reference`, nunca en claro ni en logs |
| Agente comprometido | Acceso a la infraestructura del cliente | Agente read-only, sin canal de comandos, token scoped y revocable |
| Aislamiento de tenants defectuoso | Pérdida de confianza, fin del producto | Global scope + policies + **suite de tests dedicada** |
| OneDrive sincronizando `vendor`/`node_modules` | Lentitud severa, corrupción de archivos | Volúmenes nombrados de Docker (`ADR-004`) |
| Dependencia de un solo desarrollador | Bus factor | Documentación obligatoria + decisiones en ADR |

## 10. Riesgos comerciales y mitigación

| Riesgo | Mitigación |
|---|---|
| "Ya lo hago con Excel y capturas" | El argumento es tiempo: medir horas → minutos, no features. |
| El cliente final no lee el informe | El informe debe abrir con un resumen de 10 líneas en lenguaje de negocio. |
| Percepción de que es "otro Grafana" | Posicionamiento y UX centrados en reporte; el dashboard es secundario. |
| Precio bajo con costo alto | Alcance acotado y webhook genérico en lugar de N integraciones. |
| Datos incompletos en el primer mes | Onboarding guiado: checklist de "qué falta por conectar" visible desde el día 1. |

## 11. Estructura del repositorio

```
OpsEvidence/
├── backend/                 Laravel 12 (API + jobs + reglas + reportes)
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Services/{Collectors,Normalization,Rules,Reporting,Health}/
│   │   └── Jobs/
│   ├── database/{migrations,seeders,factories}/
│   └── tests/{Feature,Unit}/
├── frontend/                Vue 3 + TS + Vite + Vuetify
├── agent/                   Linux agent (Python, read-only)
├── docs/                    Documentación y ADRs
├── scripts/                 Utilidades de desarrollo
├── docker/                  Dockerfiles y configuración
├── docker-compose.yml
└── Makefile
```

## 12. Índice de decisiones (ADR)

| ADR | Decisión |
|---|---|
| [ADR-001](adr/ADR-001-monolito-modular-laravel-spa.md) | Monolito modular Laravel + SPA Vue separada |
| [ADR-002](adr/ADR-002-multi-tenancy-account-id.md) | Multi-tenancy por `account_id` con policies |
| [ADR-003](adr/ADR-003-evidencia-append-only.md) | Evidencia append-only con `raw_data` en JSONB |
| [ADR-004](adr/ADR-004-volumenes-docker-vendor.md) | Volúmenes nombrados para `vendor` y `node_modules` |
| [ADR-005](adr/ADR-005-agente-python-readonly.md) | Agente de recolección en Python, read-only |
| [ADR-006](adr/ADR-006-webhook-generico-backup.md) | Webhook genérico como estrategia de integración de backups |
