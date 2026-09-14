"""Permite ejecutar el agente con `python3 -m opsevidence_agent`.

Es la via util cuando el servidor no tiene pip ni venv (por ejemplo, Ubuntu
20.04 sin python3-venv): basta con apuntar PYTHONPATH al directorio del paquete
y ejecutarlo como modulo, sin instalar nada.
"""

import sys

from .cli import main

if __name__ == "__main__":
    sys.exit(main())
