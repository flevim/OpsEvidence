"""Collector de uso de disco.

Emite una evidencia DISK_USAGE por sistema de archivos real, saltando
pseudosistemas (proc, sysfs, devtmpfs, etc.).
"""

from __future__ import annotations

import shutil

from ..payload import evidence_item

PROC_MOUNTS = "/proc/mounts"

PSEUDO_FS = {
    "proc",
    "sysfs",
    "devtmpfs",
    "devpts",
    "tmpfs",
    "cgroup",
    "cgroup2",
    "pstore",
    "bpf",
    "autofs",
    "securityfs",
    "debugfs",
    "tracefs",
    "fusectl",
    "configfs",
    "mqueue",
    "hugetlbfs",
    "overlay",
    "squashfs",
    # Comparticiones de VM y contenedor: no son almacenamiento del servidor.
    # Detectado al probar el agente dentro de un contenedor con un bind mount
    # de Windows, que aparecia como un disco al 66 %.
    "9p",
    "virtiofs",
    "fuse",
    "fuseblk",
    "ramfs",
    "devfs",
    "nsfs",
    "rpc_pipefs",
    "binfmt_misc",
    "efivarfs",
}

MAX_MOUNTS = 12


def collect_disks() -> list[dict]:
    mounts = _real_mounts()

    if not mounts:
        return []

    evidence: list[dict] = []
    seen_devices: set[str] = set()

    for mount in mounts:
        device = mount["device"]

        # Un mismo dispositivo puede estar montado en varios puntos (bind
        # mounts): solo se reporta la primera vez.
        if device in seen_devices:
            continue

        seen_devices.add(device)

        usage = _usage(mount["mountpoint"])

        if usage is None:
            continue

        evidence.append(
            evidence_item(
                "DISK_USAGE",
                value_numeric=usage["used_percent"],
                unit="%",
                data={
                    "mountpoint": mount["mountpoint"],
                    "device": device,
                    "filesystem": mount["filesystem"],
                    "total_bytes": usage["total"],
                    "used_bytes": usage["used"],
                    "free_bytes": usage["free"],
                    "used_percent": usage["used_percent"],
                },
            ),
        )

        if len(evidence) >= MAX_MOUNTS:
            break

    return evidence


def _real_mounts() -> list[dict]:
    try:
        with open(PROC_MOUNTS, encoding="utf-8") as handle:
            lines = handle.readlines()
    except OSError:
        return []

    mounts = []

    for line in lines:
        fields = line.split()

        if len(fields) < 3:
            continue

        device, mountpoint, filesystem = fields[0], fields[1], fields[2]

        if filesystem in PSEUDO_FS or filesystem.startswith("fuse."):
            continue

        mounts.append(
            {
                "device": device,
                "mountpoint": _decode_octal(mountpoint),
                "filesystem": filesystem,
            },
        )

    return mounts


def _usage(mountpoint: str) -> dict | None:
    try:
        total, used, free = shutil.disk_usage(mountpoint)
    except OSError:
        return None

    used_percent = round((used / total) * 100, 2) if total > 0 else 0.0

    return {"total": total, "used": used, "free": free, "used_percent": used_percent}


def _decode_octal(value: str) -> str:
    """Decodifica espacios escapados en /proc/mounts (p. ej. \040)."""
    import codecs

    try:
        return codecs.decode(value.encode("utf-8"), "unicode_escape")
    except (UnicodeDecodeError, UnicodeError):
        return value
