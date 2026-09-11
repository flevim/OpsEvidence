"""Interfaz de línea de comandos del agente."""

from __future__ import annotations

import argparse
import json
import sys

from . import __version__
from .client import EvidenceClient, EvidenceClientError
from .collectors import collect_all, collector_names
from .config import ConfigError, default_config_path, load_config, write_config_file
from .logging_setup import configure, redact
from .payload import build_payload, evidence_item, validate_evidence

EXIT_OK = 0
EXIT_ERROR = 1
EXIT_CONFIG = 2
EXIT_TRANSPORT = 3


def main(argv: list[str] | None = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)

    if not getattr(args, "command", None):
        parser.print_help()
        return EXIT_ERROR

    try:
        return args.command(args)
    except ConfigError as exc:
        print(f"error: {exc}", file=sys.stderr)
        return EXIT_CONFIG
    except KeyboardInterrupt:
        return EXIT_ERROR
    except Exception as exc:  # noqa: BLE001 - última barrera del CLI
        print(f"error inesperado: {exc}", file=sys.stderr)
        return EXIT_ERROR


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="opsevidence-agent", description="Agente read-only de OpsEvidence.")
    parser.add_argument("--version", action="version", version=f"%(prog)s {__version__}")

    subparsers = parser.add_subparsers(dest="command")

    collect = subparsers.add_parser("collect", help="Recolecta y envía evidencia una vez.")
    _add_common_flags(collect)
    collect.add_argument("--dry-run", action="store_true", help="Recolecta e imprime el payload sin enviarlo.")
    collect.add_argument("--json", action="store_true", help="Salida en JSON (para scripts).")
    collect.set_defaults(command=_cmd_collect)

    enroll = subparsers.add_parser("enroll", help="Escribe el archivo de configuración.")
    _add_common_flags(enroll)
    enroll.set_defaults(command=_cmd_enroll)

    check = subparsers.add_parser("check-config", help="Valida la configuración y muestra un resumen.")
    _add_common_flags(check)
    check.set_defaults(command=_cmd_check_config)

    collectors = subparsers.add_parser("show-collectors", help="Lista los collectors disponibles.")
    collectors.set_defaults(command=_cmd_show_collectors)

    return parser


def _add_common_flags(parser: argparse.ArgumentParser) -> None:
    parser.add_argument("--endpoint", help="URL base de OpsEvidence (p. ej. https://ops.example.com).")
    parser.add_argument("--token", help="Token de agente (también OPSEVIDENCE_TOKEN).")
    parser.add_argument("--asset", help="Nombre o id del activo (también OPSEVIDENCE_ASSET).")
    parser.add_argument("--config", help="Ruta del archivo de configuración.")
    parser.add_argument("--timeout", type=float, help="Timeout de la petición, en segundos.")
    parser.add_argument("--insecure", action="store_true", help="Deshabilita la verificación TLS (solo pruebas).")
    parser.add_argument("--verbose", action="store_true", help="Registro detallado.")


def _cmd_collect(args: argparse.Namespace) -> int:
    configure(args.verbose)
    config = load_config(vars(args))

    evidence = collect_all()

    # El latido se envía siempre: es lo que permite al servidor distinguir
    # "el servidor dejó de reportar" de "nunca se configuró el agente".
    evidence.insert(0, evidence_item("AGENT_HEARTBEAT", data={"agent_version": __version__}))

    try:
        validate_evidence(evidence)
    except ValueError as exc:
        raise ConfigError(str(exc)) from exc

    payload = build_payload(config, evidence)

    if args.dry_run:
        print(json.dumps(payload, indent=2 if not args.json else None, ensure_ascii=False))
        return EXIT_OK

    try:
        response = EvidenceClient(config).send(payload)
    except EvidenceClientError as exc:
        print(f"error: {redact(str(exc))}", file=sys.stderr)
        return EXIT_TRANSPORT

    if args.json:
        print(json.dumps(response, ensure_ascii=False))
    else:
        accepted = response.get("accepted", len(evidence))
        print(f"Evidencia enviada: {accepted} item(s) aceptado(s).")

    return EXIT_OK


def _cmd_enroll(args: argparse.Namespace) -> int:
    configure(args.verbose)

    if not args.endpoint:
        raise ConfigError("Para enroll hace falta --endpoint.")
    if not args.token:
        raise ConfigError("Para enroll hace falta --token (o la variable OPSEVIDENCE_TOKEN).")

    path = write_config_file(
        args.config or default_config_path(),
        {
            "endpoint": args.endpoint,
            "token": args.token,
            "asset": args.asset,
            "timeout": args.timeout,
            "insecure": "true" if args.insecure else "false",
        },
    )

    print(f"Configuración escrita en {path} (permisos 0600).")
    print("El token se guardó y no volverá a mostrarse. Rótalo desde el panel si lo expones.")
    return EXIT_OK


def _cmd_check_config(args: argparse.Namespace) -> int:
    configure(args.verbose)
    config = load_config(vars(args))

    print(f"endpoint: {config.require_endpoint()}")
    print(f"asset   : {config.asset or '(sin asset: el token debe estar acotado a un activo)'}")
    print(f"token   : {redact(config.require_token())}")
    print(f"timeout : {config.timeout}s")
    print(f"insecure: {'sí (¡solo pruebas!)' if config.insecure else 'no'}")

    return EXIT_OK


def _cmd_show_collectors(args: argparse.Namespace) -> int:
    for name in collector_names():
        print(name)
    return EXIT_OK
