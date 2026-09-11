"""Collectors de uptime, carga, CPU y memoria.

Todos leen de /proc y, si el sistema no lo expone, devuelven lista vacía en
lugar de fallar.
"""

from __future__ import annotations

import time

from ..payload import evidence_item

PROC_UPTIME = "/proc/uptime"
PROC_LOADAVG = "/proc/loadavg"
PROC_STAT = "/proc/stat"
PROC_MEMINFO = "/proc/meminfo"


def collect_system() -> list[dict]:
    evidence: list[dict] = []

    uptime = _read_uptime()
    load = _read_loadavg()

    if uptime is not None:
        data = {"uptime_seconds": uptime}

        if load is not None:
            data.update({"load_1m": load[0], "load_5m": load[1], "load_15m": load[2]})

        evidence.append(evidence_item("SERVER_UPTIME", value_numeric=uptime, unit="seconds", data=data))

    cpu = _read_cpu_percent()

    if cpu is not None:
        evidence.append(
            evidence_item("CPU_USAGE", value_numeric=round(cpu, 2), unit="%", data={"percent": round(cpu, 2)}),
        )

    memory = _read_memory()

    if memory is not None:
        evidence.append(
            evidence_item(
                "MEMORY_USAGE",
                value_numeric=memory["used_percent"],
                unit="%",
                data=memory,
            ),
        )

    return evidence


def _read_uptime() -> float | None:
    return _first_float(PROC_UPTIME)


def _read_loadavg() -> tuple[float, float, float] | None:
    try:
        with open(PROC_LOADAVG, encoding="utf-8") as handle:
            fields = handle.read().split()
    except OSError:
        return None

    if len(fields) < 3:
        return None

    try:
        return (float(fields[0]), float(fields[1]), float(fields[2]))
    except ValueError:
        return None


def _read_cpu_percent(interval: float = 1.0) -> float | None:
    first = _cpu_totals()

    if first is None:
        return None

    time.sleep(interval)

    second = _cpu_totals()

    if second is None:
        return None

    total = second["total"] - first["total"]
    idle = second["idle"] - first["idle"]

    if total <= 0:
        return None

    return max(0.0, min(100.0, (1 - idle / total) * 100))


def _cpu_totals() -> dict[str, float] | None:
    try:
        with open(PROC_STAT, encoding="utf-8") as handle:
            line = handle.readline()
    except OSError:
        return None

    fields = line.split()

    if len(fields) < 5 or fields[0] != "cpu":
        return None

    try:
        values = [float(value) for value in fields[1:]]
    except ValueError:
        return None

    # Las primeras cuatro columnas son user, nice, system, idle. El resto
    # (iowait, irq, softirq, steal) se suma al total.
    idle = values[3] + sum(values[4:])

    return {"total": sum(values), "idle": idle}


def _read_memory() -> dict | None:
    info: dict[str, int] = {}

    try:
        with open(PROC_MEMINFO, encoding="utf-8") as handle:
            for line in handle:
                key, _, value = line.partition(":")
                if key in {"MemTotal", "MemAvailable", "SwapTotal", "SwapFree"}:
                    info[key] = int(value.strip().split()[0])
    except (OSError, ValueError):
        return None

    if "MemTotal" not in info or "MemAvailable" not in info:
        return None

    total = info["MemTotal"]
    available = info["MemAvailable"]
    used = max(total - available, 0)
    used_percent = round((used / total) * 100, 2) if total > 0 else 0.0

    data = {
        "total_bytes": total * 1024,
        "available_bytes": available * 1024,
        "used_bytes": used * 1024,
        "used_percent": used_percent,
    }

    if "SwapTotal" in info and "SwapFree" in info:
        data["swap_total_bytes"] = info["SwapTotal"] * 1024
        data["swap_free_bytes"] = info["SwapFree"] * 1024

    return data


def _first_float(path: str) -> float | None:
    try:
        with open(path, encoding="utf-8") as handle:
            value = handle.read().split()[0]
    except (OSError, IndexError):
        return None

    try:
        return float(value)
    except ValueError:
        return None
