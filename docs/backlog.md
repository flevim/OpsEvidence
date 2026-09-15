# OpsEvidence — Backlog

> Última actualización: 2026-09-15 (Sprint 2 de administración).
> Regla: cualquier idea que no sea imprescindible para el MVP **se anota aquí y no se implementa**.

Leyenda de estados: `[ ]` pendiente · `[~]` en progreso · `[x]` hecho · `[!]` bloqueado

---

## MVP

- [x] Fase 0 — Producto, arquitectura, modelo de datos, evidencia, seguridad, ADRs
- [x] Fase 1 — Fundación: Laravel + Vue 3 + PostgreSQL + Redis + Compose + auth + healthchecks
- [x] Fase 2 — Accounts, roles, clientes, aislamiento y gestión de usuarios
- [x] Fase 3 — Assets y environments con API/UI
- [x] Fase 4 — Evidence engine: checks, check_runs, evidence, normalización
- [x] Fase 5 — Collectors HTTP + SSL con scheduling
- [x] Fase 6 — Rules engine: WEBSITE_DOWN, SSL_EXPIRING, BACKUP_FAILED, BACKUP_STALE, DISK_USAGE, CONTAINER_DOWN
- [x] Fase 7 — Linux agent (CLI Python): OS, uptime, CPU, RAM, disco
- [x] Fase 8 — Docker en el agente: containers, health, restart count, image
- [x] Fase 9 — Webhook de backup + API tokens de agente
- [x] Fase 10 — Dashboard general y dashboard por cliente
- [x] Fase 11 — Incidentes: acknowledge, resolve, ignore
- [x] Fase 12 — Reporte mensual HTML imprimible
- [~] Fase 13 — Actividades incluidas en API/informe; registro desde el panel pendiente
- [~] Fase 14 — Collector de GitHub listo; configuración desde el panel pendiente
- [~] Fase 15 — Security hardening + SECURITY.md
- [x] Fase 16 — CI con GitHub Actions (lint, tests, seguridad, build y smoke E2E)

### Criterios de aceptación del MVP

Ver §53 del brief: los 25 puntos de la Definition of Done.

---

## SPRINT 1 — ESTABILIZACIÓN

- [x] Corregir CORS del arranque rápido en `localhost:5180`
- [x] Recuperar PHPStan y retirar supresiones obsoletas del baseline
- [x] Bloquear SSRF en HTTP/SSL, incluidas redirecciones hacia redes privadas
- [x] Añadir smoke E2E: login → panel → clientes → informes
- [x] Actualizar backlog y comandos de verificación

## SPRINT 2 — ADMINISTRACIÓN

- [x] API de gestión de usuarios aislada por cuenta
- [x] Alta, edición, activación, desactivación y eliminación de usuarios desde el panel
- [x] Protección del último propietario y restricción de asignación del rol `owner`
- [x] Revocación de sesiones al desactivar o eliminar usuarios
- [x] Gestión visual de environments por cliente
- [x] Asignación de ambiente al crear activos
- [x] Cobertura feature y ampliación del smoke E2E

## PILOTO PRIVADO

- [x] Compose de piloto con frontend compilado, nginx/PHP-FPM, Caddy y HTTPS
- [x] PostgreSQL y Redis sin puertos públicos, con Redis autenticado
- [x] Inicialización sin datos demo y comando para crear el primer propietario
- [x] Backup comprimido y guía de restauración
- [ ] Desplegar en VPS real (requiere dominio/IP y acceso SSH)
- [ ] Configurar SMTP real y backup fuera del VPS

## IN PROGRESS

- [~] Fase 13 — Registro visual de actividades
- [~] Fase 14 — Configuración visual de GitHub
- [~] Fase 15 — Completar hardening y pruebas de seguridad pendientes

---

## SECURITY

Seguridad planificada y aún no implementada (el listado de lo implementado está en `docs/security.md`):

- [ ] 2FA (TOTP) para roles `owner` y `admin`
- [ ] Rotación automática de tokens de agente cada N días
- [ ] Alertas al `owner` ante login desde IP nueva
- [ ] Cifrado en reposo a nivel de base de datos
- [ ] Pentest externo antes de la primera venta relevante
- [ ] Firma de webhooks (HMAC) además del token opaco
- [ ] Lista de IPs permitidas por token de agente
- [ ] Bloqueo de cuenta tras intentos fallidos sostenidos
- [ ] Revisión formal de dependencias con `composer audit` / `npm audit` en cada release
- [ ] Rotación de `APP_KEY` con re-cifrado de credenciales
- [ ] Política de contraseñas (longitud mínima y verificación contra filtraciones conocidas)

---

## TECH DEBT

- [ ] Imagen de producción (nginx + php-fpm) con usuario sin privilegios. Hoy `docker/php/Dockerfile` es solo de desarrollo y corre como root por los volúmenes que debe escribir; Trivy lo marca con DS-0002 y está aceptado en `.trivyignore`.
- [ ] Particionado de `evidence` por rango de `collected_at` cuando supere ~100 M de filas
- [ ] Cache de los agregados del dashboard (Redis) si las consultas pasan de 500 ms
- [ ] Índices GIN en columnas JSONB que hoy no se consultan
- [ ] Retención selectiva por plan contratado
- [ ] Reemplazar `artisan serve` por nginx + php-fpm si el rendimiento lo exige
- [ ] Extraer la capa de reporting a un servicio aparte si la generación compite con la API
- [ ] Tests de carga con `k6` para los endpoints del dashboard y del agente
- [ ] Migrar de `bigint` a UUIDv7 en tablas expuestas si se requiere multi-región
- [ ] Internacionalización del informe (hoy: español)
- [ ] Zona horaria por cuenta (hoy: UTC en BD, presentación local)

---

## POST-MVP

Ordenadas por valor esperado, no por dificultad.

### Reportes y entrega
- [x] Exportación a PDF del informe
- [x] Programación mensual automática del informe
- [x] Envío por email (con el informe adjunto) al cliente final
- [ ] Plantilla de informe personalizable (logo y colores del MSP) — white label
- [ ] Marca blanca completa (dominio propio)
- [ ] Reporte de SLA con cumplimiento de disponibilidad contractual
- [ ] Reporte de RTO/RPO y verificación de restauración

### Integraciones
- [ ] Uptime Kuma (API de estado y monitores)
- [ ] Prometheus (consultas instantáneas de PromQL)
- [ ] Grafana (snapshots de paneles dentro del informe)
- [ ] Cloudflare (DNS, WAF, tráfico)
- [ ] AWS (CloudWatch, EC2, RDS)
- [ ] Azure
- [ ] GCP
- [ ] Hetzner Cloud
- [ ] DigitalOcean
- [ ] Proxmox
- [ ] Portainer
- [ ] Coolify
- [ ] Plesk
- [ ] cPanel / WHM
- [ ] Restic (integración nativa, además del webhook)
- [ ] Borg (idem)
- [ ] Veeam
- [ ] Sentry
- [ ] GitLab
- [ ] Bitbucket
- [ ] Uptime Kuma → historial de disponibilidad

### Producto
- [ ] Portal del cliente final con rol `viewer`
- [ ] Ventanas de mantenimiento (silenciar incidentes en un rango)
- [ ] Verificación de restauración de backups (prueba periódica real)
- [ ] Scoring de seguridad y gestión de vulnerabilidades
- [ ] Evidencia de cumplimiento (ANCI / ISO 27001 readiness)
- [ ] Audit log visible en la UI
- [~] Alertas por email implementadas; webhook saliente pendiente
- [ ] Línea base automática y detección de anomalías simples (sin IA)
- [x] Onboarding guiado: checklist de "qué falta conectar" por cliente, derivada del estado real (9 pasos)
- [ ] Medición del tiempo de armado del informe (instrumentación de la hipótesis)

### Comercial
- [ ] Billing con Stripe
- [ ] Autoregistro y prueba gratuita de 14 días
- [ ] Límites de plan aplicados en el código (hoy modelados pero no forzados salvo el límite de clientes)
- [ ] Pricing por cliente monitorizado
- [ ] Panel de administración de la plataforma (vista cross-account para el operador)

### Operación
- [ ] Despliegue automatizado (hoy deliberadamente manual)
- [ ] Backup y restauración de la propia BD de OpsEvidence (documentado, no automatizado)
- [ ] Terraform de la infraestructura de OpsEvidence
- [ ] Ambientes de staging

---

## IDEAS

Recogidas sin compromiso. **No implementar sin pasar la regla de admisión**
(¿recopila, normaliza, demuestra o reporta evidencia?).

- Mapa visual de dependencias entre assets
- Comparativa mes contra mes con narrativa automática ("el disco creció 21 puntos")
- Detección de tendencias (proyección de llenado de disco)
- Inventario de software instalado y matriz de versiones
- Checklist de buenas prácticas con puntuación por servidor
- Exportación de evidencia cruda a CSV para auditorías
- Vista "timeline" de todo lo ocurrido con un cliente
- Comentarios del cliente final sobre el informe
- Firma de conformidad del cliente sobre el informe recibido
- Plantillas de informe por vertical (hosting, retail, salud)
- Costeo de infraestructura (integración con facturación del proveedor cloud)
- Comparación de costos entre proveedores cloud

---

## Explícitamente fuera de alcance (no volver a proponer en el MVP)

Kubernetes · reemplazo de Prometheus · reemplazo de Grafana · logs centralizados · APM · RUM ·
tracing distribuido · SIEM · EDR · shell remoto · ejecución remota de comandos · gestión SSH ·
asistente con IA/LLM · app móvil · WhatsApp · SMS · reemplazo de PagerDuty · gestión de Terraform ·
gestión de Ansible · autofix · remediación automática.
