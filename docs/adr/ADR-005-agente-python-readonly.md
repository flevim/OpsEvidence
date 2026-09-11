# ADR-005 — Agente de recolección en Python, read-only

- **Fecha:** 2026-09-11
- **Estado:** Aceptada

## Contexto

El agente vive **dentro de la infraestructura del cliente**, por lo que su superficie
de ataque es el riesgo más directo. Debe ser mínimo, fácil de instalar y fácil de
auditar por un solo desarrollador.

## Decisión

- **Python 3** con una única dependencia de runtime (`requests`). Los datos de Linux
  se leen de `/proc` y `shutil`, sin `psutil`.
- **Read-only por diseño**: sin sockets de escucha, sin canal de comandos, sin shell
  remoto, sin ejecución de scripts descargados.
- TLS verificado por defecto; token con permisos `0600`, redactado en logs.
- Docker se consulta vía CLI (`docker inspect`), sin abrir un puerto ni reimplementar
  HTTP sobre socket Unix.

## Consecuencias

- Un token comprometido solo permite inyectar evidencia, que además se normaliza en
  el servidor (el agente no decide estados).
- Instalación sin toolchain: `pip install` y un binario de consola.

## Alternativas descartadas

- **Go:** binario único y sin runtime, pero más código para el mismo alcance y menos
  familiar para iterar rápido.
- **Agente con comandos remotos / autofix:** rechazado explícitamente por el brief.
