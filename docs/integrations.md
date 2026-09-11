# Integraciones

OpsEvidence se conecta a las herramientas que el cliente ya usa. El valor está en
**recopilar, normalizar y reportar**, no en reimplementar la monitorización.

## Implementadas

| Integración | Cómo | Qué aporta |
|---|---|---|
| HTTP / Website | Collector de la plataforma | disponibilidad, tiempo de respuesta |
| SSL | Collector de la plataforma | vencimiento del certificado |
| Linux | Agente read-only | host, uptime, CPU, RAM, discos |
| Docker | Agente (CLI read-only) | contenedores, health, restart count |
| GitHub | Collector de la plataforma | último workflow/deploy |
| Backup | Webhook genérico | resultado de cualquier script de backup |

## Webhook de backup

Una sola interfaz cubre restic, borg, pg_dump, Veeam o cualquier script que el
cliente ya tenga:

```bash
curl -X POST https://ops.example.com/api/webhooks/backup/TOKEN \
  -H 'Content-Type: application/json' \
  -d '{"source": "postgres-production", "status": "success", "size": 1258291200, "duration_seconds": 83}'
```

El token es opaco, va en la URL, se guarda hasheado y se revoca en el panel.

## Añadir una integración

1. `integrations` almacena la configuración (no secreta) y las credenciales
   **cifradas** (`encrypted:array`). La API solo expone `has_credentials`.
2. Si es un collector de la plataforma: implementa `Collector` y regístralo.
3. Si es por webhook: reutiliza `ApiToken` + un endpoint de ingesta.

## Futuras (backlog)

Uptime Kuma, Prometheus, Grafana, Cloudflare, AWS, Azure, GCP, Hetzner,
DigitalOcean, Proxmox, Portainer, Coolify, Plesk, cPanel, Restic, Borg, Veeam,
Sentry, GitLab, Bitbucket. **No se implementan** hasta que el MVP esté validado.
