"""Collector de actualizaciones pendientes (best effort).

Nunca ejecuta nada que modifique el sistema: solo usa modos de simulación o
consulta. Si el gestor de paquetes no está disponible, devuelve lista vacía.
"""

from __future__ import annotations

import re
import shutil
import subprocess

from ..payload import evidence_item


def collect_pending_updates() -> list[dict]:
    if shutil.which("apt-get"):
        return _apt()

    if shutil.which("dnf"):
        return _dnf()

    return []


def _apt() -> list[dict]:
    try:
        completed = subprocess.run(
            ["apt-get", "-s", "upgrade"],
            capture_output=True,
            text=True,
            timeout=30,
            check=False,
        )
    except (OSError, subprocess.TimeoutExpired):
        return []

    lines = completed.stdout.splitlines()

    total = len([line for line in lines if line.startswith("Inst ")])
    security = len(
        [line for line in lines if line.startswith("Inst ") and re.search(r"\bsecurity\b", line, re.IGNORECASE)],
    )

    if total == 0 and " 0 upgraded" not in completed.stdout:
        return []

    return [
        evidence_item(
            "PENDING_UPDATES",
            value_numeric=security,
            unit="count",
            data={"security_count": security, "total_count": total},
        ),
    ]


def _dnf() -> list[dict]:
    try:
        completed = subprocess.run(
            ["dnf", "-q", "check-update"],
            capture_output=True,
            text=True,
            timeout=60,
            check=False,
        )
    except (OSError, subprocess.TimeoutExpired):
        return []

    # `dnf check-update` devuelve 100 cuando hay actualizaciones y 0 cuando no.
    # El conteo se hace sobre las líneas de paquetes, no sobre el código.
    lines = [line for line in completed.stdout.splitlines() if line.strip()]

    total = max(len(lines) - 1, 0)  # la última línea suele ser un resumen
    security = len([line for line in lines if "security" in line.lower()])

    return [
        evidence_item(
            "PENDING_UPDATES",
            value_numeric=security,
            unit="count",
            data={"security_count": security, "total_count": total},
        ),
    ]
