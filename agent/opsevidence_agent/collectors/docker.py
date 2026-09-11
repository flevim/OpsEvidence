"""Collector de contenedores Docker (read-only).

Usa el CLI de Docker, nunca ejecuta nada que modifique el sistema. Si Docker
no está instalado, el daemon no responde o el usuario no tiene permisos,
devuelve lista vacía.
"""

from __future__ import annotations

import json
import shutil
import subprocess
from typing import Any

from ..payload import evidence_item


def collect_docker() -> list[dict]:
    docker_bin = shutil.which("docker")

    if docker_bin is None:
        return []

    containers = _inspect_containers(docker_bin)

    if not containers:
        return []

    return [
        evidence_item("DOCKER_CONTAINER_STATUS", data={"containers": containers}),
        evidence_item("DOCKER_HEALTH", data={"containers": containers}),
    ]


def _inspect_containers(docker_bin: str) -> list[dict]:
    try:
        ids_result = subprocess.run(
            [docker_bin, "ps", "-aq"],
            capture_output=True,
            text=True,
            timeout=15,
            check=False,
        )
    except (OSError, subprocess.TimeoutExpired):
        return []

    if ids_result.returncode != 0 or not ids_result.stdout.strip():
        return []

    ids = ids_result.stdout.split()

    try:
        inspect = subprocess.run(
            [docker_bin, "inspect", "--format", "{{json .}}", *ids],
            capture_output=True,
            text=True,
            timeout=30,
            check=False,
        )
    except (OSError, subprocess.TimeoutExpired):
        return []

    if inspect.returncode != 0:
        return []

    containers = []

    for line in inspect.stdout.splitlines():
        summary = _summarize(line)

        if summary is not None:
            containers.append(summary)

    return containers


def _summarize(line: str) -> dict | None:
    line = line.strip()

    if not line:
        return None

    try:
        raw: dict[str, Any] = json.loads(line)
    except json.JSONDecodeError:
        return None

    state: dict[str, Any] = raw.get("State") or {}
    config: dict[str, Any] = raw.get("Config") or {}
    health: dict[str, Any] = state.get("Health") or {}

    name = raw.get("Name", "").lstrip("/")

    return {
        "name": name,
        "id": (raw.get("Id") or "")[:12],
        "image": config.get("Image", ""),
        "state": state.get("Status", "unknown"),
        "status": state.get("Status", ""),
        "health": health.get("Status") or "none",
        "restart_count": int(raw.get("RestartCount") or 0),
        "started_at": state.get("StartedAt", ""),
    }
