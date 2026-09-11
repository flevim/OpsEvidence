# Collectors

Los collectors son la etapa **COLLECT** del pipeline. Cada uno produce `EvidencePayload`
cruda; el backend la normaliza, la guarda y la analiza.

## Principio

Antes de escribir un collector nuevo se evalúa, en este orden:

1. ¿Existe una **API** que exponga el dato? → consumirla.
2. ¿Existe un **webhook** o script que el cliente ya ejecute? → recibirlo.
3. ¿Existe un **archivo** o comando local estándar? → leerlo con el agente.
4. Solo entonces: escribir lógica propia.

## Collectors de la plataforma

Viven en `backend/app/Services/Collectors/` y los ejecuta `RunCheckJob`.

| Collector | Tipos de check | Fuente |
|---|---|---|
| `HttpCheckCollector` | `HTTP_STATUS`, `HTTP_RESPONSE_TIME` | `Http::get` con timeout y sin verificación deshabilitada |
| `SslCheckCollector` | `SSL_EXPIRATION` | handshake TLS vía `stream_socket_client`, solo lee el certificado |
| `GithubWorkflowCollector` | `GITHUB_WORKFLOW` | API de GitHub Actions (último run) |

Registro: `CollectorRegistry` (`app/Services/Collectors/CollectorRegistry.php`).

## Collectors del agente

Viven en `agent/opsevidence_agent/collectors/` y el agente los ejecuta en el servidor.

| Collector | Tipos de evidencia | Fuente |
|---|---|---|
| `host` | `HOST_INFO` | `/etc/os-release`, kernel |
| `system` | `SERVER_UPTIME`, `CPU_USAGE`, `MEMORY_USAGE` | `/proc` |
| `disks` | `DISK_USAGE` | `/proc/mounts` + `shutil.disk_usage` |
| `updates` | `PENDING_UPDATES` | `apt-get -s upgrade` / `dnf check-update` |
| `docker` | `DOCKER_CONTAINER_STATUS`, `DOCKER_HEALTH` | `docker inspect` (read-only) |

## Añadir un collector

Para un collector de la plataforma:

1. Implementa `App\Services\Collectors\Contracts\Collector`.
2. Registra la clase en `CollectorRegistry::COLLECTORS`.
3. Añade el tipo a `CheckType` si no existe y su collector devuelve los payloads.

Para un collector del agente:

1. Escribe una función `collect_x() -> list[dict]` en `agent/opsevidence_agent/collectors/`.
2. Regístrala en `collectors/__init__.py`.
3. El payload sigue el contrato de `docs/evidence-model.md`.

## Reglas de oro

- Un fallo de red que **demuestra que el activo está mal** es evidencia `CRITICAL`.
- Un fallo que **impide medir** (handshake roto, API con 500) es `FAILED`.
- Nunca se descarta un fallo en silencio: queda en `check_runs.error_message` y en
  `checks.last_error`.
