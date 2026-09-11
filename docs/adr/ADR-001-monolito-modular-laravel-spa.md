# ADR-001 — Monolito modular Laravel + SPA Vue separada

- **Fecha:** 2026-09-10
- **Estado:** Aceptada

## Contexto

OpsEvidence debe ser construido y mantenido por **un solo desarrollador**, con un MVP vendible en
semanas. El sistema tiene, por naturaleza, partes con perfiles de carga distintos: una API de
lectura/escritura para el panel, y una carga de trabajo asíncrona de recolección periódica.

Las alternativas consideradas eran:

1. **Monolito Laravel con SPA Vue separada** (dos artefactos, un repositorio).
2. Monolito con Blade/Inertia (un solo artefacto).
3. Backend y frontend como servicios independientes desplegables por separado.
4. Microservicios (API + collector service + rules service + report service).

## Decisión

**Adoptamos la opción 1: un monolito Laravel que expone una API REST, consumida por una SPA Vue 3
construida con Vite, en el mismo repositorio.**

La carga de trabajo asíncrona (collectors, reglas, informes) se ejecuta como **Laravel Jobs sobre
Redis** dentro del mismo código base, en procesos separados (`worker`, `scheduler`) lanzados desde
**la misma imagen Docker**.

El monolito se organiza internamente por dominio (`Services/{Collectors,Normalization,Rules,
Reporting,Health}`), no por capa técnica, para que un módulo pueda extraerse en el futuro sin
reescribir la aplicación.

## Consecuencias

**Positivas**

- Un solo lenguaje, un solo framework, un solo modelo de tests, una sola imagen Docker.
- Los jobs comparten modelos, policies y validaciones con la API: cero duplicación de contrato.
- El scheduler y la cola son nativos: no hay que construir infraestructura de orquestación.
- El despliegue es `docker compose up`. Sin Kubernetes, sin service mesh.
- La SPA separada permite servir el frontend como estáticos y cachearlos en CDN sin tocar el backend.

**Negativas**

- El frontend y el backend se despliegan como dos artefactos (se mitiga: ambos salen del mismo repo
  y del mismo `docker compose`).
- Un monolito mal organizado se degrada en "carpeta de controllers". Se mitiga con la organización
  por dominio y con la regla de que **la lógica vive en Services, no en Controllers**.
- Escalar la recolección obliga a escalar la API si se usa la misma imagen. Se mitiga: `worker` y
  `scheduler` son servicios independientes en Compose y se escalan por separado
  (`docker compose up -d --scale worker=3`).

## Alternativas descartadas

- **Blade/Inertia:** excelente para CRUD, pero el producto exige un dashboard con estado en vivo,
  filtros y tablas paginadas; la SPA lo resuelve con menos fricción y mejor UX.
- **Servicios independientes:** duplica contratos, validaciones y despliegue para un equipo de una
  persona. Costo sin beneficio en esta escala.
- **Microservicios:** resuelven problemas organizacionales (equipos autónomos) que aquí no existen.
  Introducirían consistencia eventual, trazabilidad distribuida y operaciones que el MVP no puede
  pagar.
