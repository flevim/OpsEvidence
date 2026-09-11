"""Registro de collectors del agente.

Cada collector es una función que devuelve una lista de items de evidencia.
Deben ser tolerantes: si la fuente no existe, devuelven lista vacía.
"""

from __future__ import annotations

import logging
from typing import Callable

from .disks import collect_disks
from .docker import collect_docker
from .host import collect_host_info
from .system import collect_system
from .updates import collect_pending_updates

logger = logging.getLogger(__name__)

Collector = Callable[[], list[dict]]

COLLECTORS: list[tuple[str, Collector]] = [
    ("host_info", collect_host_info),
    ("system", collect_system),
    ("disks", collect_disks),
    ("pending_updates", collect_pending_updates),
    ("docker", collect_docker),
]


def collector_names() -> list[str]:
    return [name for name, _ in COLLECTORS]


def collect_all() -> list[dict]:
    """Ejecuta todos los collectors, aislando el fallo de cada uno."""
    evidence: list[dict] = []

    for name, collector in COLLECTORS:
        try:
            evidence.extend(collector())
        except Exception:  # noqa: BLE001 - un collector roto no debe tumbar el envío
            logger.debug("collector %s falló", name, exc_info=True)

    return evidence
