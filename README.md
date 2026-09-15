# OpsEvidence

Evidencia técnica de infraestructura, convertida en informes claros para tus clientes.

OpsEvidence **no reemplaza** Grafana, Prometheus, Uptime Kuma, GitHub ni Docker: se
conecta a esas herramientas, recopila lo relevante y lo convierte en **evidencia
histórica, indicadores y reportes** que un MSP, freelancer o equipo de TI puede
entregar a sus clientes.

> No monitorea: **demuestra**.

## Qué resuelve

Al cerrar el mes, un proveedor de TI necesita responder: qué se hizo, qué funcionó,
qué falló, cuánto estuvo disponible el servicio, cuándo se ejecutaron los backups y
qué riesgos quedan pendientes. Esa evidencia suele estar dispersa en terminales,
correos, GitHub, Docker y scripts. OpsEvidence la centraliza.

## Arranque rápido

Requisitos: Docker y Docker Compose.

```bash
cp .env.example .env
docker compose up -d --build --wait
```

| Recurso | URL |
|---|---|
| Panel (frontend) | http://localhost:5180 |
| API | http://localhost:8010/api |
| Health | http://localhost:8010/api/health |

> Los puertos por defecto son **5180** (frontend) y **8010** (API) para no colisionar
> con otros proyectos que usen 5173/8000. Cámbialos en `.env`.

Credenciales de demostración (se siembran al primer arranque):

```
owner@opsevidence.test / password
```

## Qué puedes probar hoy

- Panel general con estado, incidentes y clientes.
- Alta de clientes, activos y comprobaciones.
- Ambientes de producción, staging, desarrollo u otros dentro de cada cliente.
- Gestión de usuarios, roles y accesos desde **Usuarios**.
- Ejecución de checks, recepción de evidencia y seguimiento de problemas.
- Generación, vista, PDF y envío de informes mensuales.
- Tokens de agente desde **Ajustes** y correo de prueba en Mailpit: http://localhost:8025.

Para llevar la prueba a un VPS con dominio y HTTPS, sigue
[Despliegue de piloto privado](docs/pilot-deployment.md).

Si todavía no tienes dominio, la misma guía incluye el modo LAN para probar desde
`http://192.168.1.13:5180` sin exponer el servicio a Internet.

## Stack

- **Backend:** Laravel 13 (API REST + Sanctum + colas Redis) · PostgreSQL 16.
- **Frontend:** Vue 3 · TypeScript · Vite · Pinia · Vue Router · Vuetify 3.
- **Agente:** Python 3 (read-only), sin dependencias de runtime más que `requests`.
- **Infraestructura:** Docker Compose. Sin Kubernetes.

## Estructura

```
backend/     Laravel: API, motor de evidencia, reglas, reportes, tests
frontend/    SPA Vue 3 + Vuetify
agent/       Agente Linux read-only (Python)
docker/      Dockerfiles y scripts de init
docs/        Producto, arquitectura, seguridad, guías y ADRs
.github/     CI (GitHub Actions) y Dependabot
```

## Pipeline

```
COLLECT → NORMALIZE → STORE → ANALYZE → REPORT
```

Los collectors (HTTP/SSL, agente Linux, Docker, GitHub, webhook de backups) producen
datos crudos; el backend los normaliza a estados canónicos (`HEALTHY`, `WARNING`,
`CRITICAL`, `UNKNOWN`, `FAILED`), los guarda de forma append-only y el motor de reglas
deriva problemas e incidentes. El informe mensual es el entregable.

## Tests y CI

```bash
# Backend
docker compose exec backend php artisan test
docker compose exec backend vendor/bin/pint --test
docker compose exec backend vendor/bin/phpstan analyse

# Frontend
docker compose exec frontend npm run lint
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run test

# Smoke E2E (requiere Chromium de Playwright y el Compose levantado)
cd frontend && npm run test:e2e

# Agente
cd agent && .venv/bin/python -m pytest -q && .venv/bin/python -m ruff check .
```

CI (GitHub Actions) ejecuta lint, type checks, tests, build, Gitleaks y Trivy sobre
backend, frontend y agente.

## Documentación

| Documento | Contenido |
|---|---|
| [docs/product.md](docs/product.md) | Problema, público, valor y alcance del MVP |
| [docs/architecture.md](docs/architecture.md) | Arquitectura y decisiones |
| [docs/database.md](docs/database.md) | Modelo de datos revisado |
| [docs/evidence-model.md](docs/evidence-model.md) | Evidencia, normalización y frescura |
| [docs/security.md](docs/security.md) | Threat model y controles |
| [docs/collectors.md](docs/collectors.md) | Cómo recolecta cada fuente |
| [docs/linux-agent.md](docs/linux-agent.md) | Instalación y seguridad del agente |
| [docs/rules-engine.md](docs/rules-engine.md) | Reglas e incidentes |
| [docs/reporting.md](docs/reporting.md) | Informes y health score |
| [docs/development.md](docs/development.md) | Flujo de desarrollo |
| [docs/first-real-server.md](docs/first-real-server.md) | Guía para el primer servidor real |
| [docs/backlog.md](docs/backlog.md) | Roadmap y deuda |

## Estado

MVP funcional: autenticación, multi-tenancy, clientes, activos, checks, evidencia,
reglas, incidentes, actividades, dashboard, informes y agente Linux. Pendiente:
validación con usuarios reales, billing y el resto del backlog.
