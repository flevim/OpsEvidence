#!/bin/sh
#
# Instalador del agente de OpsEvidence en un servidor Linux.
#
# Validado instalando y recolectando en Debian 13 (contenedor) contra un backend real.
#
# Uso:
#   sudo ./install.sh https://ops.mi-dominio.com ops_xxxxxxxxxxxx web-server-01
#
# Variables opcionales:
#   SOURCE=./agent        Origen del paquete (por defecto, el repositorio publico).
#   WITH_DOCKER=1         Da al usuario del agente acceso al socket de Docker.
#                         ATENCION: pertenecer al grupo docker equivale a root
#                         sobre el host. Actívalo solo si aceptas ese trade-off.
#   WITH_SYSTEMD=0        No instalar el timer de systemd.
#
set -eu

ENDPOINT="${1:-}"
TOKEN="${2:-}"
ASSET="${3:-}"
SOURCE="${SOURCE:-git+https://github.com/flevin/OpsEvidence.git@master#subdirectory=agent}"
AGENT_HOME=/opt/opsevidence-agent
AGENT_BIN=/usr/local/bin/opsevidence-agent
CONF_DIR=/etc/opsevidence
CONF_FILE="$CONF_DIR/agent.yml"

if [ "$(id -u)" -ne 0 ]; then
  echo "error: ejecuta este script como root (sudo)." >&2
  exit 1
fi

if [ -z "$ENDPOINT" ] || [ -z "$TOKEN" ] || [ -z "$ASSET" ]; then
  echo "uso: sudo $0 <endpoint> <token> <asset>" >&2
  exit 1
fi

command -v python3 >/dev/null 2>&1 || { echo "error: falta python3." >&2; exit 1; }

python3 -c 'import sys; sys.exit(0 if sys.version_info >= (3, 8) else 1)' \
  || { echo "error: se requiere Python 3.8 o superior." >&2; exit 1; }

echo "==> Usuario de sistema 'opsevidence'"
if ! id opsevidence >/dev/null 2>&1; then
  useradd --system --no-create-home --shell /usr/sbin/nologin opsevidence
fi

echo "==> Instalando el agente"
INSTALLED=0

# Se prefiere un entorno aislado: en Debian/Ubuntu modernos instalar en el
# python del sistema falla por PEP 668 y ademas ensucia el sistema.
if python3 -m venv "$AGENT_HOME" >/dev/null 2>&1; then
  "$AGENT_HOME/bin/pip" install --quiet --upgrade pip >/dev/null 2>&1 || true
  "$AGENT_HOME/bin/pip" install --quiet "$SOURCE"
  ln -sf "$AGENT_HOME/bin/opsevidence-agent" "$AGENT_BIN"
  INSTALLED=1
fi

if [ "$INSTALLED" -eq 0 ]; then
  echo "    (python3-venv no disponible; se instala en el python del sistema)"
  python3 -m pip install --quiet --break-system-packages "$SOURCE" 2>/dev/null \
    || python3 -m pip install --quiet "$SOURCE"
fi

if [ "${WITH_DOCKER:-0}" = "1" ]; then
  echo "==> Acceso al socket de Docker (equivalente a root sobre el host)"
  if getent group docker >/dev/null 2>&1; then
    usermod -aG docker opsevidence
  else
    echo "    aviso: no existe el grupo docker; se omite." >&2
  fi
fi

echo "==> Configurando"
install -d -m 0750 -o root -g opsevidence "$CONF_DIR"
# El token solo existe aqui, y solo lo puede leer el usuario del agente.
"$AGENT_BIN" enroll --endpoint "$ENDPOINT" --token "$TOKEN" --asset "$ASSET"
chown root:opsevidence "$CONF_FILE"
chmod 0640 "$CONF_FILE"

echo "==> Verificando"
"$AGENT_BIN" check-config

if [ "${WITH_SYSTEMD:-1}" = "1" ] && command -v systemctl >/dev/null 2>&1; then
  echo "==> Timer de systemd (cada 5 minutos)"
  cat > /etc/systemd/system/opsevidence-agent.service <<'UNIT'
[Unit]
Description=OpsEvidence evidence agent (collect once)
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
User=opsevidence
Group=opsevidence
ExecStart=/usr/local/bin/opsevidence-agent collect

NoNewPrivileges=yes
PrivateTmp=yes
PrivateDevices=yes
ProtectSystem=strict
ProtectHome=read-only
ReadOnlyPaths=/
CapabilityBoundingSet=
AmbientCapabilities=
RestrictAddressFamilies=AF_UNIX AF_INET AF_INET6
RestrictRealtime=yes
LockPersonality=yes
MemoryDenyWriteExecute=yes
ProtectKernelTunables=yes
ProtectKernelModules=yes
ProtectControlGroups=yes
ProtectHostname=yes
SystemCallArchitectures=native

[Install]
WantedBy=multi-user.target
UNIT

  cat > /etc/systemd/system/opsevidence-agent.timer <<'UNIT'
[Unit]
Description=OpsEvidence evidence agent timer

[Timer]
OnBootSec=2min
OnUnitActiveSec=5min
AccuracySec=30s
Persistent=true

[Install]
WantedBy=timers.target
UNIT

  systemctl daemon-reload
  systemctl enable --now opsevidence-agent.timer >/dev/null 2>&1 || \
    echo "    aviso: no se pudo activar el timer; revisa 'systemctl status opsevidence-agent.timer'." >&2
fi

echo
echo "Listo."
echo "  Revisa que enviaria:  sudo -u opsevidence $AGENT_BIN collect --dry-run"
echo "  Envia ahora:          sudo -u opsevidence $AGENT_BIN collect"
echo
echo "El agente es de solo lectura: no abre puertos, no acepta comandos remotos"
echo "y no ejecuta nada que venga del servidor."
