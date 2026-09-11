"""Logging con redacción de secretos.

El token de agente nunca debe aparecer en logs ni en mensajes de error. La
forma más segura es redactarlo en la capa de logging, de modo que ni el código
ni las librerías de terceros puedan filtrarlo accidentalmente.
"""

from __future__ import annotations

import logging
import re
from typing import Any

TOKEN_PATTERN = re.compile(r"(ops_[A-Za-z0-9_-]{8,})")
REDACTED = "ops_****"


class RedactFilter(logging.Filter):
    """Reemplaza cualquier cosa con forma de token por un marcador."""

    def filter(self, record: logging.LogRecord) -> bool:
        record.msg = self._redact(record.msg)

        if record.args:
            args = []
            for arg in record.args:
                args.append(self._redact(arg) if isinstance(arg, str) else arg)
            record.args = tuple(args)

        return True

    @staticmethod
    def _redact(value: Any) -> Any:
        if isinstance(value, str):
            return TOKEN_PATTERN.sub(REDACTED, value)
        return value


def configure(verbose: bool = False) -> None:
    """Configura el logging raíz: stderr, con redacción y nivel ajustable."""
    level = logging.DEBUG if verbose else logging.INFO

    handler = logging.StreamHandler()
    handler.setFormatter(logging.Formatter("%(levelname)s %(message)s"))
    handler.addFilter(RedactFilter())

    root = logging.getLogger()
    root.handlers = [handler]
    root.setLevel(level)

    # Evita que requests registre cabeceras (donde viaja el token).
    logging.getLogger("urllib3").setLevel(logging.WARNING)
    logging.getLogger("requests").setLevel(logging.WARNING)


def redact(value: str) -> str:
    """Redacta un valor aislado (por ejemplo, para mensajes de error)."""
    return TOKEN_PATTERN.sub(REDACTED, value)
