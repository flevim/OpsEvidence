# Primer servidor real

Guía para validar OpsEvidence con un servidor Linux propio, **sin exponer datos
sensibles** y sin dar al agente más acceso del necesario.

## 1. Antes de empezar

- Usa un VPS de pruebas, no un servidor de un cliente real.
- Crea un usuario de sistema dedicado para el agente (sin shell de login).
- Verifica que el servidor tenga `python3` (≥ 3.9) y, si quieres datos Docker,
  `docker` accesible por ese usuario.

## 2. En OpsEvidence

1. Crea (o usa) un cliente y un activo de tipo `SERVER` (o `CONTAINER_HOST`).
2. Ve a **Ajustes → Tokens de agente** y emite un token **acotado a ese cliente y
   activo**. Guarda el token: solo se muestra una vez.
3. Anota la URL pública de tu OpsEvidence (debe ser `https://`).

## 3. En el servidor

```bash
# Crear usuario dedicado
sudo useradd --system --no-create-home --shell /usr/sbin/nologin opsevidence

# Instalar el agente
sudo pip3 install .
# o desde el repo:
#   sudo /usr/local/bin/python3 -m venv /opt/opsevidence && ...

# Enroll (escribe /etc/opsevidence/agent.yml con permisos 0600)
sudo /usr/local/bin/opsevidence-agent enroll \
  --endpoint https://ops.tu-dominio.com \
  --token ops_xxxxxxxx \
  --asset web-server-01

# Verificar qué enviaría, sin enviar nada
sudo -u opsevidence /usr/local/bin/opsevidence-agent collect --dry-run

# Primer envío real
sudo -u opsevidence /usr/local/bin/opsevidence-agent collect
```

## 4. Programar

```bash
sudo cp systemd/opsevidence-agent.service systemd/opsevidence-agent.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now opsevidence-agent.timer
```

La unidad corre cada 5 minutos, con el sistema de archivos en read-only, sin
capacidades y sin sockets de escucha.

## 5. Comprobar que funcionó

En OpsEvidence, abre el cliente → **Evidencia**. Deberías ver `AGENT_HEARTBEAT`,
`HOST_INFO`, `CPU_USAGE`, `MEMORY_USAGE`, `DISK_USAGE` y, si Docker está disponible,
`DOCKER_CONTAINER_STATUS` y `DOCKER_HEALTH`.

## 6. Higiene

- Si el token se expone, revócalo en el panel y emite uno nuevo.
- El agente no guarda más secretos que el token y su configuración.
- Para desinstalar: detén el timer, borra el usuario y `/etc/opsevidence/agent.yml`.

## Qué NO hace el agente

No abre puertos, no acepta comandos, no ejecuta scripts del servidor y no envía
variables de entorno ni el contenido de archivos de configuración.
