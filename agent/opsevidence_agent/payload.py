"""Construcción y validación del payload enviado al servidor.

El contrato es exactamente el que espera el endpoint POST /api/agent/evidence
del backend de OpsEvidence. El agente reporta datos crudos: el estado
(HEALTHY/WARNING/CRITICAL) lo decide el servidor a partir de los umbrales del
check configurado.
"""

from __future__ import annotations

import datetime
import socket
from typing import Any

AGENT_VERSION = "1.0.0"
MAX_EVIDENCE_ITEMS = 200

EVIDENCE_TYPES = {
    "HOST_INFO",
    "AGENT_HEARTBEAT",
    "SERVER_UPTIME",
    "CPU_USAGE",
    "MEMORY_USAGE",
    "DISK_USAGE",
    "PENDING_UPDATES",
    "DOCKER_CONTAINER_STATUS",
    "DOCKER_HEALTH",
}


def utcnow_iso() -> str:
    return datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def hostname() -> str:
    return socket.gethostname() or "unknown-host"


def evidence_item(
    type_: str,
    *,
    data: dict[str, Any] | None = None,
    value_numeric: float | int | None = None,
    unit: str | None = None,
    value_text: str | None = None,
    raw_status: str | None = None,
) -> dict[str, Any]:
    """Construye un item de evidencia con la forma exacta del contrato."""
    item: dict[str, Any] = {"type": type_}

    if data is not None:
        item["data"] = data
    if value_numeric is not None:
        item["value_numeric"] = value_numeric
    if unit is not None:
        item["unit"] = unit
    if value_text is not None:
        item["value_text"] = value_text
    if raw_status is not None:
        item["raw_status"] = raw_status

    return item


def build_payload(config, evidence: list[dict[str, Any]]) -> dict[str, Any]:
    """Ensambla el cuerpo del POST, acotando el número de items."""
    evidence = [item for item in evidence if isinstance(item.get("type"), str)]
    evidence = evidence[:MAX_EVIDENCE_ITEMS]

    return {
        "asset": config.asset,
        "agent_version": AGENT_VERSION,
        "hostname": hostname(),
        "collected_at": utcnow_iso(),
        "evidence": evidence,
    }


def validate_evidence(evidence: list[dict[str, Any]]) -> None:
    """Valida la forma de los items antes de enviarlos (falla temprano y claro)."""
    for item in evidence:
        type_ = item.get("type")

        if type_ not in EVIDENCE_TYPES:
            raise ValueError(f"Tipo de evidencia no válido: {type_!r}")

        if "value_numeric" in item and not isinstance(item["value_numeric"], (int, float)):
            raise ValueError(f"value_numeric debe ser numérico en {type_!r}")
