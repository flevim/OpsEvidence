# ADR-002 — Multi-tenancy por `account_id` con policies

- **Fecha:** 2026-09-10
- **Estado:** Aceptada

## Contexto

OpsEvidence sirve a varios MSP; el aislamiento entre cuentas es crítico. Un tenant
jamás debe ver clientes, activos, checks, evidencia ni reportes de otro.

## Decisión

Base de datos compartida, esquema compartido, discriminador `account_id`
**desnormalizado** en toda tabla de negocio. Defensa en tres capas:

1. **Global scope** (`BelongsToAccount`) que filtra por `account_id` cuando hay un
   `AccountContext` activo.
2. **Policies** explícitas que verifican la pertenencia.
3. **Middleware** `EnsureTenantOwnership` que devuelve **404** (no 403) si un route
   binding resuelve un recurso de otro tenant, para no filtrar su existencia.

La capa 3 y la suite `TenantIsolationTest` son las que garantizan que las capas 1 y 2
sigan funcionando ante consultas nuevas.

## Consecuencias

- Un solo predicado de aislamiento, trivial de auditar.
- Sin N esquemas ni migraciones por tenant (costo desproporcionado a esta escala).
- `account_id` desnormalizado evita JOINs de tres niveles en dashboard e informes.

## Alternativas descartadas

- **Schema por tenant:** N migraciones y fricción operativa sin beneficio en el MVP.
- **Base de datos por tenant:** complica el despliegue y los backups sin necesidad.
