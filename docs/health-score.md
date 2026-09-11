# Infrastructure Health Score

Puntuación técnica de salud de la infraestructura, de 0 a 100.

## Fórmula

```
score = Σ (componente_con_datos × peso) / Σ (peso_de_componentes_con_datos)
```

Cada componente se puntúa de 0 a 100. El resultado se redondea a entero.

## Pesos (v1)

| Componente | Peso | Fuente |
|---|---|---|
| Disponibilidad | 30 | % de comprobaciones HTTP `HEALTHY` |
| Backups | 20 | % de backups exitosos |
| Recursos | 15 | peor estado de disco/memoria/CPU |
| Contenedores | 10 | % en ejecución |
| SSL | 10 | certificados vigentes |
| Actualizaciones | 10 | penalización por updates de seguridad |
| Incidentes | 5 | penalización por incidentes abiertos |

Configurables en `config/opsevidence.php` → `health_score.weights`.

## Reglas no negociables

1. **Un componente sin datos no suma ni resta**: se excluye y se informa aparte.
2. **Los incidentes no cuentan como "dato"**: una cuenta sin ninguna evidencia y
   cero incidentes no puede salir 100/100 "Excelente".
3. Si no hay **ningún** componente con datos, el score es `null` (sin dato), no cero.
4. El score **nunca** se presenta como garantía ni SLA; el informe muestra el desglose.

## Bandas

| Score | Etiqueta |
|---|---|
| ≥ 90 | Excelente |
| 75–89 | Bueno |
| 60–74 | Requiere atención |
| 40–59 | En riesgo |
| < 40 | Crítico |

## Versionado

Cada cálculo guarda `version` (hoy `1.0`). Si cambias la fórmula, incrementa la
versión y documenta el cambio; los informes ya generados conservan su snapshot y su
score congelado.
