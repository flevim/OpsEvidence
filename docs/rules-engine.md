# Motor de reglas

El motor de reglas es la etapa **ANALYZE**. Evalúa la última evidencia de cada activo
y deriva **incidentes** (problemas detectados) cuando una condición se incumple.

## Diseño

- `RulesEngine` construye un `RuleContext` (una foto de la infraestructura) y ejecuta
  el catálogo de reglas.
- Cada regla es una función **pura** sobre ese contexto: no consulta base de datos y es
  trivialmente testeable.
- `IncidentManager` convierte los incumplimientos en incidentes.

```
última evidencia (DISTINCT ON asset_id, type)
   │
   ▼
RuleContext ──► 13 reglas ──► RuleViolation[]
   │
   ▼
IncidentManager ──► abrir / actualizar / resolver incidentes
```

## Reglas y umbrales por defecto

| Regla | Evidencia | Umbral por defecto |
|---|---|---|
| `WEBSITE_DOWN` | `HTTP_STATUS` | status CRITICAL |
| `SSL_EXPIRING` | `SSL_EXPIRATION` | warning 30 días · critical 7 días |
| `BACKUP_FAILED` | `BACKUP_STATUS` | status CRITICAL |
| `BACKUP_STALE` | `BACKUP_STATUS` | sin backup en 36 h |
| `DISK_USAGE` | `DISK_USAGE` | warning 80 % · critical 90 % |
| `CONTAINER_DOWN` | `DOCKER_CONTAINER_STATUS` | contenedor no `running` |
| `DOCKER_UNHEALTHY` | docker | health `unhealthy` |
| `HIGH_MEMORY` | `MEMORY_USAGE` | warning 85 % · critical 95 % |
| `HIGH_CPU` | `CPU_USAGE` | warning 85 % · critical 95 % |
| `PENDING_SECURITY_UPDATES` | `PENDING_UPDATES` | ≥ 1 seguridad (warning) |
| `SERVER_UNREACHABLE` | `AGENT_HEARTBEAT` | sin latido en 2 h |
| `GITHUB_WORKFLOW_FAILED` | `GITHUB_WORKFLOW` | conclusion `failure` |
| `COLLECTOR_FAILING` | `checks.consecutive_failures` | ≥ 3 |

Los umbrales son configurables por cuenta y por cliente (`rule_settings`).

## Incidentes

- **Un incidente vivo por (cliente, regla, activo)**, garantizado por índice único
  parcial. Una regla que se repite cada 5 minutos no genera 288 incidentes al día.
- Estados: `open`, `acknowledged`, `resolved`, `ignored`.
- Si la condición desaparece, el incidente se **resuelve automáticamente**.
- Un incidente reconocido a mano no se reabre: si la condición vuelve, se crea uno
  nuevo (el historial cuenta la verdad).

## Distinción importante

`CRITICAL` significa **el activo está mal** (disco al 95 %, sitio caído).
`FAILED` significa **no pudimos recolectar** (timeout, credencial inválida). Los
incidentes de recolección se tratan aparte (`COLLECTOR_FAILING`): son problema de
OpsEvidence, no del cliente.

## Añadir una regla

1. Crea una clase en `app/Services/Rules/Rules/` que implemente `Rule`.
2. Registra la clase en `RulesEngine::RULES`.
3. Añade la clave a `RuleKey` si no existe.
4. Escribe un test en `tests/Feature/RulesAndReportTest.php`.
