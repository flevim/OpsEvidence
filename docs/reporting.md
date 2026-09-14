# Reportes

El informe mensual es **el entregable** del producto: es lo que el cliente final
recibe y lo que justifica el trabajo del proveedor. Ver `docs/product.md` §5.2.

## Generación

`POST /api/reports` con `client_id`, `period_start` y `period_end`:

1. `ReportMetricsCollector` agrega la evidencia del periodo (disponibilidad, backups,
   contenedores, SSL, actualizaciones, deployments, incidentes, actividades).
2. `HealthScoreCalculator` calcula el **Infrastructure Health Score**.
3. `ReportBuilder` congela un **snapshot** (activos, incidentes, actividades y
   desglose del score) dentro del informe.
4. `GET /api/reports/{id}/html` renderiza el informe imprimible.

El snapshot hace que un informe enviado se pueda reproducir idéntico meses después,
aunque la evidencia cruda haya sido purgada por retención.

## Principios

- **Lenguaje de negocio, no paneles técnicos.** "El disco está al 91,8 % y quedan
  18 GB" + recomendación, no "load average / iowait".
- **"Sin datos" no es cero.** Un check sin evidencia se reporta como *Sin datos* y
  se excluye del score; nunca se presenta un 0 falso.
- **El score se explica.** Siempre va acompañado del desglose por componente.

## Fórmula del score

Ver [docs/health-score.md](health-score.md).

## Entrega

| Acción | Endpoint | Qué hace |
|---|---|---|
| Ver informe | `GET /api/reports/{id}/html` | HTML responsive, para revisar o imprimir |
| Descargar PDF | `GET /api/reports/{id}/pdf` | PDF A4 generado con dompdf |
| Enviar por correo | `POST /api/reports/{id}/send` | Envía el informe al contacto del cliente con el PDF adjunto y lo marca como enviado |
| Marcar enviado | `POST /api/reports/{id}/mark-sent` | Registro manual, para cuando se entrega por otra vía |

El PDF usa una plantilla propia (`reports/monthly-pdf.blade.php`) porque dompdf no
interpreta grid ni flexbox: el documento imprimible se construye con tablas.

El comando `opsevidence:generate-monthly-reports` genera el informe del mes
anterior para cada cliente activo y corre el día 1 de cada mes. Con `--send` los
envía; por defecto solo los genera, porque **enviar al cliente es una decisión
humana**, no automática.

En desarrollo los correos se ven en **Mailpit**: http://localhost:8025

## Marcar como enviado

`POST /api/reports/{id}/mark-sent` registra `sent_at`. Es la instrumentación de la
hipótesis de negocio: mide si el informe realmente se entrega al cliente.

## Futuro

White label, firma de conformidad del cliente y portal de cliente final están en el
backlog.
