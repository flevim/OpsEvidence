"""Cliente HTTPS del agente.

Reglas de seguridad que este módulo garantiza:
  - Verificación de certificado TLS obligatoria (salvo --insecure, que es
    explícitamente para pruebas locales y está documentado).
  - El token viaja solo en la cabecera Authorization y nunca en mensajes de
    error ni en la URL.
  - Sin reintentos sobre 4xx: un 401 no se reintenta jamás.
"""

from __future__ import annotations

import time
from typing import Any

import requests

from . import logging_setup
from .config import AgentConfig

RETRYABLE_STATUS = {429, 500, 502, 503, 504}
MAX_ATTEMPTS = 3


class EvidenceClientError(Exception):
    """Error de transporte o del servidor."""


class EvidenceClient:
    def __init__(self, config: AgentConfig):
        self.config = config
        self.endpoint = config.require_endpoint()

    def send(self, payload: dict[str, Any]) -> dict[str, Any]:
        """Envía la evidencia y devuelve la respuesta JSON del servidor."""
        url = f"{self.endpoint}/api/agent/evidence"
        headers = {
            "Authorization": f"Bearer {self.config.require_token()}",
            "Content-Type": "application/json",
        }

        attempt = 0

        while True:
            attempt += 1

            try:
                response = requests.post(
                    url,
                    json=payload,
                    headers=headers,
                    timeout=self.config.timeout,
                    verify=not self.config.insecure,
                )
            except requests.RequestException as exc:
                if attempt < MAX_ATTEMPTS:
                    time.sleep(_backoff(attempt))
                    continue

                raise EvidenceClientError(
                    f"No se pudo conectar con OpsEvidence: {logging_setup.redact(str(exc))}",
                ) from exc

            if response.status_code in RETRYABLE_STATUS and attempt < MAX_ATTEMPTS:
                time.sleep(_backoff(attempt))
                continue

            if response.status_code == 401:
                raise EvidenceClientError("El token fue rechazado (401). Verifica que sea válido y no esté revocado.")

            if response.status_code >= 400:
                raise EvidenceClientError(
                    f"OpsEvidence respondió {response.status_code}: {logging_setup.redact(response.text[:300])}",
                )

            try:
                return response.json()
            except ValueError as exc:
                raise EvidenceClientError("La respuesta del servidor no era JSON válido.") from exc


def _backoff(attempt: int) -> float:
    return min(2 ** attempt, 8)
