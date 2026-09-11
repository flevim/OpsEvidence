# OpsEvidence — Documento de Producto

> Estado: Fase 0 (definición). Versión 1.0 — 2026-09-10.

## 1. Resumen ejecutivo

OpsEvidence es una plataforma B2B que **recopila evidencia técnica** de la infraestructura que un
proveedor de TI administra para sus clientes, la **normaliza**, la **almacena históricamente** y la
convierte en **informes claros** que justifican el trabajo realizado y el estado del servicio.

La frase que resume el producto:

> OpsEvidence no monitorea. OpsEvidence **demuestra**.

No reemplaza Grafana, Prometheus, Uptime Kuma, GitHub, Docker ni las herramientas que el cliente ya
usa. Se conecta a ellas, extrae lo relevante y lo convierte en evidencia histórica y reportes.

---

## 2. Problema

Un MSP o sysadmin dedica el mes a: mantener servidores, revisar disponibilidad, verificar backups,
actualizar sistemas, renovar certificados, vigilar contenedores, ejecutar deployments y responder
incidentes. Al cerrar el mes, el problema no es el trabajo: es **demostrarlo**.

La evidencia existe pero está dispersa en terminales, correos, GitHub, Uptime Kuma, Docker,
scripts sueltos, logs y capturas de pantalla. Armarlo manualmente cuesta entre 2 y 8 horas por
cliente y por mes, se hace con prisa y con frecuencia se entrega incompleto o poco convincente.

**Consecuencias medibles:**
- Horas facturables perdidas en trabajo administrativo no facturado.
- Renovaciones de contrato discutidas por falta de evidencia ("¿qué hicieron este mes?").
- Riesgos que escalan porque nadie consolidó la señal (certificados, discos, backups).
- Imposibilidad de demostrar cumplimiento de SLA.

---

## 3. Público objetivo (ICP)

Prioridad de entrada, de mayor a menor urgencia:

| Segmento | Tamaño típico | Por qué compra |
|---|---|---|
| MSP pequeños | 3–15 clientes | El informe mensual es obligación contractual; hoy es manual. |
| Freelancer DevOps / Sysadmin | 1–5 clientes | Pierde noches armando reportes que no le pagan. |
| Agencia web con hosting | 10–30 sitios | Necesita demostrar cuidado del hosting sin ser experta en DevOps. |
| Equipo TI interno | 1 organización | Necesita reportes de infraestructura para gerencia. |

**Explícitamente fuera del alcance inicial:** enterprises con NOC, equipos SRE, organizaciones con
compliance formal (SOC2/ISO) o que ya operan una plataforma de observabilidad madura.

---

## 4. Propuesta de valor

Abrir OpsEvidence y ver, por cliente y por periodo, algo como:

```
Cliente:      Empresa ABC
Periodo:      Agosto 2026

Disponibilidad ................ 99,96 %
Servicios monitorizados ....... 14
Backups exitosos .............. 29/30
Última restauración probada ... hace 4 días
Certificados por vencer ....... 1
Servidores con updates ........ 3
Contenedores activos .......... 23/24
Deployments ................... 7
Incidentes .................... 2
Vulnerabilidades críticas ..... 0
```

Y, con un clic, el **informe listo para enviar al cliente final**.

**Promesa central:** reducir de horas a minutos el tiempo de armado del informe mensual, y
entregar un documento que el cliente final entienda sin ser técnico.

---

## 5. Análisis crítico

### 5.1 Dónde está realmente el valor

El valor no está en recolectar datos — eso ya lo hacen las herramientas existentes. El valor está
en tres cosas que hoy nadie hace bien:

1. **Consolidar** evidencia de fuentes heterogéneas en un modelo común.
2. **Traducir** lenguaje técnico a lenguaje de negocio comprensible para el cliente final.
3. **Demostrar** con histórico: no "el disco está al 91 %", sino "el disco creció de 70 % a 91 % en
   90 días, esto es una tendencia, y esto es lo que hay que hacer".

### 5.2 El verdadero MVP: el informe, no el dashboard

Esta es la decisión de producto más importante del documento.

El dashboard es la herramienta **del técnico**. El informe es el entregable **que el cliente paga**.
Un MSP no cobra por mirar un dashboard; cobra por el informe mensual y por el trabajo que
representa.

Por lo tanto:

- El **reporting es camino crítico**, no una fase estética posterior.
- Toda feature debe justificarse por su contribución al informe: ¿alimenta el informe, mejora su
  precisión, o reduce el tiempo de armarlo? Si no, va al backlog.
- El dashboard debe existir, pero para **optimizar el técnico** (detectar qué está mal ahora),
  no para impresionar.

Consecuencia práctica: se construye el pipeline completo **end-to-end** con dos o tres fuentes
(HTTP/SSL, Linux agent, backup webhook) antes de añadir la cuarta o quinta integración. Un producto
que reporta con 3 fuentes es vendible; uno que monitorea 10 fuentes y no reporta, no lo es.

### 5.3 Los collectors son el costo oculto

Cada integración es deuda de mantenimiento permanente (APIs que cambian, autenticaciones que
rotan, versiones que rompen). Con un solo desarrollador, el presupuesto de integraciones es el
recurso más escaso.

Estrategia:

- **Preferir fuentes que el cliente ya expone** sobre construir agentes.
- **Un agente read-only, mínimo y genérico** (Linux + Docker) en vez de N agentes.
- **Un webhook genérico para backups** en vez de 20 integraciones de backup. Si el cliente tiene un
  script, con un `curl` ya está integrado. Esto es enorme: una sola interfaz cubre restic, borg,
  pg_dump, Veeam y cualquier cosa que el cliente ya tenga.
- Cualquier integración nueva es "trivial o al backlog".

### 5.4 El riesgo silencioso: distinguir "0" de "sin datos"

Un reporte que dice "Backups: 0/0" o "Disponibilidad: 0 %" porque el collector nunca corrió es
**peor que no tener reporte**: destruye la confianza en el producto y en el MSP.

Regla de diseño obligatoria: todo dato mostrado lleva asociado su **estado de frescura**
(`fresh` / `stale` / `never_collected`). Un check sin datos se muestra como **"Sin datos"**, nunca
como un cero. Esto aplica a la API, al dashboard y al informe.

### 5.5 Cuidado con el "Infrastructure Health Score"

Un número único es vendible pero peligroso: si un cliente ve "98/100" y luego cae un servicio, el
producto queda desacreditado. Mitigaciones de diseño (no negociables):

- El score **debe ser explicable**: siempre acompañado del desglose por componente y de los
  factores que lo bajan.
- Nunca presentarlo como garantía ni como SLA.
- Fórmula configurable, documentada y versionada.
- Un componente sin datos **no suma ni resta**; se excluye del cálculo y se marca aparte.

### 5.6 Lo que NO debe ser OpsEvidence

Existe una fuerza natural que empuja el producto hacia "otro Grafana". Se rechaza explícitamente.

**Regla de admisión de features:**

> ¿Esto recopila, normaliza, demuestra o reporta evidencia?
> Si la respuesta es NO, no se implementa: va al backlog.

---

## 6. Alcance del MVP

### Dentro

1. Autenticación y sesión.
2. Accounts (tenants) con usuarios y roles.
3. Clientes y environments.
4. Assets tipados.
5. Checks configurables por tipo.
6. Ejecución de checks (`check_runs`) y recolección de evidencia.
7. Capa de normalización de estados.
8. Almacenamiento histórico (snapshots) sin sobrescritura.
9. Dashboard general y por cliente.
10. Rules engine con detección de problemas.
11. Incidentes simples (open / ack / resolved / ignored).
12. Actividades manuales del técnico.
13. **Informe mensual por cliente, HTML imprimible.**
14. Collectors: HTTP/SSL, Linux agent, Docker (vía agente), Backup (webhook).
15. Multi-tenancy con aislamiento testeado.
16. Docker Compose, tests, CI, documentación.

### Fuera (al backlog, ver `docs/backlog.md`)

- Billing / Stripe.
- Kubernetes, Prometheus, Grafana como fuentes.
- Logs centralizados, APM, RUM, tracing distribuido.
- Shell remoto, ejecución remota de comandos, gestión SSH.
- IA / LLMs.
- App móvil, WhatsApp, SMS, PagerDuty.
- Terraform / Ansible management.
- Auto-remediación.
- Portal de cliente final con login (el informe se **envía**, no se consulta).

---

## 7. Modelo de negocio

Sin implementación de billing en el MVP, pero con límites por plan modelados desde el inicio
(`accounts.plan`, contador de clientes) para poder activarlos después sin refactor.

| Plan | Límite de clientes | Precio objetivo |
|---|---|---|
| Freelancer | hasta 5 | USD 19–29 / mes |
| MSP | hasta 25 | USD 49–79 / mes |
| MSP Pro | sin límite práctico | USD 99–149 / mes |

Alternativa evaluada: pricing por cliente monitoreado (USD 3–6 / cliente / mes). Se considera
compatible: el plan define el techo, el uso define el costo. Decisión final post-validación.

**Elasticidad del precio:** el valor se mide en horas ahorradas. Si el producto ahorra 4 horas al
mes a una tarifa de USD 40/h, el valor generado es USD 160/mes: el precio de USD 79 deja margen
más que suficiente para ser obvio.

---

## 8. Hipótesis a validar y métricas de éxito

**Hipótesis principal:**

> Una pequeña empresa de soporte TI estaría dispuesta a pagar por automatizar la evidencia y los
> reportes que entrega mensualmente a sus clientes.

**Experimento de validación:** 5 MSP, cada uno administrando entre 10 y 30 clientes, operando un
ciclo mensual completo con OpsEvidence.

**Métricas de éxito del experimento:**

| Métrica | Umbral de éxito |
|---|---|
| MSP que completan un ciclo mensual | ≥ 3 de 5 |
| Reducción del tiempo de armado del informe | ≥ 60 % vs. línea base |
| Informes efectivamente enviados a clientes finales | ≥ 80 % de los generados |
| Clientes finales que responden o renuevan sin fricción | señal cualitativa |
| Intención de pago declarada | ≥ 3 de 5 |

**Instrumentación mínima:** tiempo entre "abrir el módulo de reportes" y "marcar informe como
enviado". Es la métrica que prueba o refuta la hipótesis. Se registra desde el MVP.

---

## 9. Roadmap por fases

| Fase | Contenido | Resultado |
|---|---|---|
| 0 | Producto, arquitectura, modelo, amenazas, backlog, ADR | Rumbo fijado |
| 1 | Fundación: Laravel, Vue, Postgres, Redis, Compose, auth | `docker compose up` funciona |
| 2 | Accounts, usuarios, roles, clientes, tenancy | Aislamiento testeado |
| 3 | Environments y assets | Inventario real |
| 4 | Evidence engine + normalización | Datos entran y se normalizan |
| 5 | HTTP + SSL | Primera recolección automática |
| 6 | Rules engine | Problemas detectados solos |
| 7 | Linux agent | Métricas de servidores propios |
| 8 | Docker en el agente | Estado de contenedores |
| 9 | Backup webhook | Cualquier backup integrado con un curl |
| 10 | Dashboard | Visibilidad |
| 11 | Incidentes | Gestión del problema |
| 12 | **Reportes HTML imprimibles** | **El entregable vendible** |
| 13 | Actividades | Trabajo realizado en el informe |
| 14 | GitHub Actions | Deployments en el informe |
| 15 | Security hardening | Confianza para vender |
| 16 | CI | Calidad sostenible |

---

## 10. Principios de producto

1. **Integrar, no reemplazar.** Si ya existe una herramienta, nos conectamos.
2. **El informe es el producto.** Todo lo demás lo alimenta.
3. **Traducir, no volcar.** El cliente final no sabe qué es `iowait`.
4. **Nunca mostrar 0 cuando no hay dato.** Frescura explícita siempre.
5. **Histórico es valor.** No sobrescribir; acumular.
6. **Read-only por defecto.** Ninguna capacidad de ejecución remota.
7. **Un solo desarrollador.** Simple, barato y mantenible gana a elegante y complejo.
8. **Aislar tenants es sagrado.** Nunca cruzar datos entre accounts.
9. **El MVP termina.** Las ideas nuevas van al backlog, no al sprint.
