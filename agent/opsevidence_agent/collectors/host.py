"""Collector de información básica del host."""

from __future__ import annotations

import os
import platform
import socket

from ..payload import evidence_item

OS_RELEASE_PATH = "/etc/os-release"


def collect_host_info() -> list[dict]:
    release = _read_os_release()

    if not release:
        return []

    return [
        evidence_item(
            "HOST_INFO",
            data={
                "hostname": socket.gethostname(),
                "os": release.get("PRETTY_NAME") or release.get("NAME", "linux"),
                "kernel": platform.release(),
                "architecture": platform.machine(),
            },
        ),
    ]


def _read_os_release() -> dict[str, str]:
    if not os.path.exists(OS_RELEASE_PATH):
        return {}

    release: dict[str, str] = {}

    with open(OS_RELEASE_PATH, encoding="utf-8") as handle:
        for line in handle:
            line = line.strip()

            if not line or "=" not in line:
                continue

            key, _, value = line.partition("=")
            release[key] = value.strip().strip('"')

    return release
