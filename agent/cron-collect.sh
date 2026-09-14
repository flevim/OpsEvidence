#!/bin/sh
#
# Recolecta y envía evidencia. Pensado para ejecutarse desde cron cada 5 minutos.
#
# Se usa PYTHONPATH en vez de instalar el paquete porque muchos servidores en
# producción no tienen pip ni python3-venv, y el agente no necesita root para
# leer /proc (Docker sí requiere pertenecer al grupo docker, que se comprueba
# aparte).
#
# Instalación:
#   1. Copiar el agente a ~/opsevidence-agent
#   2. ./opsevidence-agent/bin/... o enroll con --endpoint/--token/--asset
#   3. Añadir a cron:  */5 * * * * $HOME/opsevidence-agent/cron-collect.sh >/dev/null 2>&1
#
set -eu

AGENT_DIR="${OPSEVIDENCE_AGENT_DIR:-$HOME/opsevidence-agent}"
PYTHON_BIN="$(command -v python3 || echo /usr/bin/python3)"

exec env PYTHONPATH="$AGENT_DIR" "$PYTHON_BIN" -m opsevidence_agent collect
