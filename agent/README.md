# OpsEvidence Agent

Agente **read-only** que recolecta información técnica de un servidor Linux y la
envía por HTTPS a OpsEvidence.

El agente **no** abre puertos, **no** acepta comandos del servidor, **no**
ofrece shell remoto y **no** ejecuta scripts descargados. Su único canal es
saliente: un `POST` de evidencia.

## Qué recolecta

| Collector | Tipo de evidencia | Fuente |
|---|---|---|
| host_info | `HOST_INFO` | `/etc/os-release`, kernel |
| system | `SERVER_UPTIME` | `/proc/uptime`, `/proc/loadavg` |
| system | `CPU_USAGE` | `/proc/stat` (dos muestras) |
| system | `MEMORY_USAGE` | `/proc/meminfo` |
| disks | `DISK_USAGE` (por montaje real) | `/proc/mounts` + `shutil.disk_usage` |
| pending_updates | `PENDING_UPDATES` | `apt-get -s upgrade` o `dnf check-update` (best effort) |
| docker | `DOCKER_CONTAINER_STATUS`, `DOCKER_HEALTH` | `docker inspect` (read-only) |
| — | `AGENT_HEARTBEAT` (siempre) | el propio agente |

Si una fuente no existe (por ejemplo, Windows o un servidor sin Docker), ese
collector devuelve vacío y no falla el envío.

## Formato del payload

El agente reporta **datos crudos**: el estado (`HEALTHY`, `WARNING`, `CRITICAL`)
lo decide el servidor a partir de los umbrales del check. Un token comprometido
no puede inyectar un "todo está bien" falso.

```json
{
  "asset": "web-server-01",
  "agent_version": "1.0.0",
  "hostname": "web-01",
  "collected_at": "2026-09-10T03:01:43Z",
  "evidence": [
    {"type": "AGENT_HEARTBEAT", "data": {"agent_version": "1.0.0"}},
    {"type": "DISK_USAGE", "value_numeric": 91.8, "unit": "%",
     "data": {"mountpoint": "/", "used_percent": 91.8, "total_bytes": 0}}
  ]
}
```

## Instalación

```bash
python3 -m venv .venv
.venv/bin/pip install .
```

Esto instala el ejecutable `opsevidence-agent`.

## Configuración (`enroll`)

```bash
opsevidence-agent enroll \
  --endpoint https://ops.tu-dominio.com \
  --token ops_xxxxxxxxxxxxxxxx \
  --asset web-server-01
```

Escribe `/etc/opsevidence/agent.yml` (o `~/.config/opsevidence/agent.yml` si no
hay permisos) con permisos `0600`. El token se guarda una vez y no vuelve a
mostrarse.

La configuración se resuelve con esta precedencia:
parámetros del CLI → variables de entorno → archivo → valores por defecto.

## Uso

```bash
# Recolectar y enviar una vez
opsevidence-agent collect

# Ver qué se enviaría, sin mandar nada
opsevidence-agent collect --dry-run

# Validar la configuración (token redactado)
opsevidence-agent check-config

# Listar collectors
opsevidence-agent show-collectors
```

Variables de entorno: `OPSEVIDENCE_ENDPOINT`, `OPSEVIDENCE_TOKEN`,
`OPSEVIDENCE_ASSET`, `OPSEVIDENCE_CONFIG`, `OPSEVIDENCE_TIMEOUT`.

## Programar el envío

Con systemd (recomendado):

```bash
sudo cp systemd/opsevidence-agent.service systemd/opsevidence-agent.timer /etc/systemd/system/
sudo useradd --system --no-create-home opsevidence
sudo systemctl enable --now opsevidence-agent.timer
```

El timer ejecuta el agente cada 5 minutos. La unidad está endurecida:
`NoNewPrivileges`, sistema de archivos read-only, sin capacidades, sin sockets
de escucha.

O con cron:

```
*/5 * * * * /usr/local/bin/opsevidence-agent collect
```

## Seguridad

- TLS con verificación de certificado **obligatoria** (`--insecure` solo para
  pruebas locales).
- Token hasheado en el servidor, revocable y con alcance por cliente/activo.
- El token nunca aparece en logs: se redacta en la propia capa de logging.
- Reintentos con backoff solo ante errores de red y 5xx; nunca ante 401.
- El agente no lee archivos arbitrarios ni envía variables de entorno.

## Desarrollo

```bash
python -m venv .venv
.venv/bin/pip install -e ".[dev]"
.venv/bin/python -m pytest
.venv/bin/python -m ruff check .
```

Los tests usan fixtures y mocks; no tocan servidores reales.
