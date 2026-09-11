"""Resolución de configuración.

Precedencia, de mayor a menor prioridad:
  1. Parámetros del CLI.
  2. Variables de entorno (OPSEVIDENCE_ENDPOINT, OPSEVIDENCE_TOKEN,
     OPSEVIDENCE_ASSET, OPSEVIDENCE_TIMEOUT, OPSEVIDENCE_INSECURE).
  3. Archivo de configuración (flag --config, OPSEVIDENCE_CONFIG, o rutas por
     defecto).
  4. Valores por defecto.

El archivo se escribe con permisos 0600 y nunca guarda el token en claro en
ningún lado distinto de ese archivo.
"""

from __future__ import annotations

import os
from collections.abc import Mapping
from dataclasses import dataclass
from pathlib import Path
from typing import Any

DEFAULT_CONFIG_PATHS = (
    "/etc/opsevidence/agent.yml",
    "~/.config/opsevidence/agent.yml",
)

ENV_ENDPOINT = "OPSEVIDENCE_ENDPOINT"
ENV_TOKEN = "OPSEVIDENCE_TOKEN"
ENV_ASSET = "OPSEVIDENCE_ASSET"
ENV_CONFIG = "OPSEVIDENCE_CONFIG"
ENV_TIMEOUT = "OPSEVIDENCE_TIMEOUT"
ENV_INSECURE = "OPSEVIDENCE_INSECURE"


class ConfigError(Exception):
    """Configuración inválida o incompleta."""


@dataclass
class AgentConfig:
    endpoint: str | None = None
    token: str | None = None
    asset: str | None = None
    timeout: float = 15.0
    insecure: bool = False

    def require_endpoint(self) -> str:
        if not self.endpoint:
            raise ConfigError(
                "Falta el endpoint. Configúralo con --endpoint, OPSEVIDENCE_ENDPOINT o "
                "'opsevidence-agent enroll'.",
            )

        endpoint = self.endpoint.rstrip("/")

        if not endpoint.startswith(("http://", "https://")):
            raise ConfigError("El endpoint debe comenzar por http:// o https://.")

        return endpoint

    def require_token(self) -> str:
        if not self.token:
            raise ConfigError(
                "Falta el token. Configúralo con --token, OPSEVIDENCE_TOKEN o "
                "'opsevidence-agent enroll'.",
            )
        return self.token


def load_config(cli: Mapping[str, Any] | None = None) -> AgentConfig:
    """Construye la configuración efectiva aplicando la cadena de precedencia."""
    cli = dict(cli or {})

    config_path = _resolve_config_path(cli.get("config"))
    file_values = read_config_file(config_path) if config_path else {}

    timeout_raw = (
        cli.get("timeout")
        or os.environ.get(ENV_TIMEOUT)
        or file_values.get("timeout")
        or "15"
    )

    return AgentConfig(
        endpoint=cli.get("endpoint") or os.environ.get(ENV_ENDPOINT) or file_values.get("endpoint"),
        token=cli.get("token") or os.environ.get(ENV_TOKEN) or file_values.get("token"),
        asset=cli.get("asset") or os.environ.get(ENV_ASSET) or file_values.get("asset"),
        timeout=_parse_float(timeout_raw, 15.0),
        insecure=_parse_bool(
            cli.get("insecure", os.environ.get(ENV_INSECURE) or file_values.get("insecure")),
        ),
    )


def read_config_file(path: str | Path) -> dict[str, str]:
    """Lee un archivo de configuración en un subconjunto simple de YAML.

    Solo se aceptan pares `clave: valor` en el nivel raíz. Cualquier otra
    construcción (listas, anidamiento) produce un error claro: no queremos un
    parser YAML completo como dependencia del agente.
    """
    path = Path(path).expanduser()

    if not path.exists():
        raise ConfigError(f"El archivo de configuración no existe: {path}")

    values: dict[str, str] = {}

    for number, line in enumerate(path.read_text(encoding="utf-8").splitlines(), start=1):
        stripped = line.strip()

        if not stripped or stripped.startswith("#"):
            continue

        if stripped.startswith("- ") or stripped.startswith("  ") or line != line.lstrip():
            raise ConfigError(f"Configuración no soportada en {path}:{number} (solo se admite 'clave: valor').")

        if ":" not in stripped:
            raise ConfigError(f"Línea inválida en {path}:{number}.")

        key, _, value = stripped.partition(":")
        values[key.strip()] = value.strip().strip("\"'")

    return values


def write_config_file(path: str | Path, values: Mapping[str, Any]) -> Path:
    """Escribe la configuración con permisos 0600."""
    path = Path(path).expanduser()
    path.parent.mkdir(parents=True, exist_ok=True)

    lines = [f"{key}: {value}" for key, value in values.items() if value not in (None, "")]
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")
    os.chmod(path, 0o600)

    return path


def default_config_path() -> Path:
    """Ruta donde `enroll` escribe por defecto: sistema si se puede, si no usuario."""
    system = Path("/etc/opsevidence/agent.yml")

    if system.parent.exists() and os.access(system.parent, os.W_OK):
        return system

    return Path("~/.config/opsevidence/agent.yml").expanduser()


def _resolve_config_path(explicit: Any) -> Path | None:
    if explicit:
        return Path(str(explicit)).expanduser()

    env = os.environ.get(ENV_CONFIG)

    if env:
        return Path(env).expanduser()

    for candidate in DEFAULT_CONFIG_PATHS:
        path = Path(candidate).expanduser()
        if path.exists():
            return path

    return None


def _parse_float(value: Any, default: float) -> float:
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def _parse_bool(value: Any) -> bool:
    if isinstance(value, bool):
        return value

    return str(value).strip().lower() in {"1", "true", "yes", "on"}
