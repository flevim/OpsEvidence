<?php

namespace App\Services\Reporting;

/**
 * Infrastructure Health Score.
 *
 * Formula documentada en docs/health-score.md:
 *  - Cada componente se puntua de 0 a 100.
 *  - El score final es la media ponderada de los componentes CON DATOS.
 *  - Un componente sin datos se EXCLUYE (no puntua cero) y se informa aparte.
 *
 * Nunca se presenta como garantia ni como SLA: el informe muestra siempre el
 * desglose que lo explica.
 */
class HealthScoreCalculator
{
    /**
     * @param  array<string, mixed>  $metrics
     * @return array{score: int|null, band: string|null, band_label: string, components: array<string, array<string, mixed>>, excluded: array<int, string>, version: string}
     */
    public function calculate(array $metrics): array
    {
        $weights = config('opsevidence.health_score.weights');
        $bands = config('opsevidence.health_score.bands');

        $components = [
            'availability' => $this->availabilityComponent($metrics['availability'] ?? []),
            'backups' => $this->backupComponent($metrics['backups'] ?? []),
            'resources' => $this->resourcesComponent($metrics['checks'] ?? []),
            'containers' => $this->containerComponent($metrics['containers'] ?? []),
            'ssl' => $this->sslComponent($metrics['ssl'] ?? []),
            'updates' => $this->updatesComponent($metrics['updates'] ?? []),
            'incidents' => $this->incidentComponent($metrics['incidents'] ?? []),
        ];

        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $excluded = [];
        $dataBearingComponents = 0;

        foreach ($components as $key => $component) {
            if ($component['score'] === null) {
                $excluded[] = $key;

                continue;
            }

            // Los incidentes no cuentan como "dato de monitorizacion": una
            // cuenta sin ninguna evidencia tendria cero incidentes y saldria
            // como infraestructura perfecta. Es justo lo que este producto no
            // debe hacer (ver docs/product.md 5.4).
            if ($key !== 'incidents') {
                $dataBearingComponents++;
            }

            $weight = (float) ($weights[$key] ?? 0);
            $weightedSum += $component['score'] * $weight;
            $weightTotal += $weight;
        }

        $hasEnoughData = $dataBearingComponents > 0 && $weightTotal > 0;
        $score = $hasEnoughData ? (int) round($weightedSum / $weightTotal) : null;

        return [
            'score' => $score,
            'band' => $score !== null ? $this->band($score, $bands) : null,
            'band_label' => $score !== null ? $this->bandLabel($this->band($score, $bands)) : 'Sin datos suficientes',
            'components' => $components,
            'excluded' => $excluded,
            'version' => (string) config('opsevidence.health_score.version'),
        ];
    }

    /**
     * @param  array<string, mixed>  $availability
     * @return array<string, mixed>
     */
    private function availabilityComponent(array $availability): array
    {
        $percentage = $availability['percentage'] ?? null;

        return [
            'label' => 'Disponibilidad',
            'score' => $percentage === null ? null : round((float) $percentage, 1),
            'detail' => $percentage === null
                ? 'Sin comprobaciones HTTP en el periodo'
                : number_format((float) $percentage, 2, ',', '.').' % de disponibilidad',
        ];
    }

    /**
     * @param  array<string, mixed>  $backups
     * @return array<string, mixed>
     */
    private function backupComponent(array $backups): array
    {
        $total = (int) ($backups['total'] ?? 0);
        $ok = (int) ($backups['ok'] ?? 0);
        $failed = (int) ($backups['failed'] ?? 0);

        if ($total === 0) {
            return ['label' => 'Backups', 'score' => null, 'detail' => 'Sin backups registrados en el periodo'];
        }

        $rate = $ok / $total;
        $score = round($rate * 100, 1);

        if ($failed > 0) {
            $score = max(0, $score - min($failed * 5, 25));
        }

        return [
            'label' => 'Backups',
            'score' => $score,
            'detail' => sprintf('%d de %d backups correctos%s', $ok, $total, $failed > 0 ? ", {$failed} fallidos" : ''),
        ];
    }

    /**
     * Recursos: se apoya en el peor estado observado de disco, memoria y CPU.
     *
     * @param  array<string, mixed>  $checks
     * @return array<string, mixed>
     */
    private function resourcesComponent(array $checks): array
    {
        $byStatus = $checks['by_status'] ?? [];
        $healthy = (int) ($byStatus['HEALTHY'] ?? 0);
        $warning = (int) ($byStatus['WARNING'] ?? 0);
        $critical = (int) ($byStatus['CRITICAL'] ?? 0);
        $total = $healthy + $warning + $critical;

        if ($total === 0) {
            return ['label' => 'Recursos y servicios', 'score' => null, 'detail' => 'Sin observaciones en el periodo'];
        }

        $score = (($healthy + $warning * 0.6) / $total) * 100;

        return [
            'label' => 'Recursos y servicios',
            'score' => round($score, 1),
            'detail' => sprintf('%d observaciones saludables, %d con atención, %d críticas', $healthy, $warning, $critical),
        ];
    }

    /**
     * @param  array<string, mixed>  $containers
     * @return array<string, mixed>
     */
    private function containerComponent(array $containers): array
    {
        if (! ($containers['available'] ?? false)) {
            return ['label' => 'Contenedores', 'score' => null, 'detail' => 'Sin información de contenedores'];
        }

        $total = (int) ($containers['total'] ?? 0);
        $running = (int) ($containers['running'] ?? 0);

        if ($total === 0) {
            return ['label' => 'Contenedores', 'score' => null, 'detail' => 'No se detectaron contenedores'];
        }

        return [
            'label' => 'Contenedores',
            'score' => round(($running / $total) * 100, 1),
            'detail' => "{$running} de {$total} contenedores en ejecución",
        ];
    }

    /**
     * @param  array<string, mixed>  $ssl
     * @return array<string, mixed>
     */
    private function sslComponent(array $ssl): array
    {
        if (! ($ssl['available'] ?? false)) {
            return ['label' => 'Certificados SSL', 'score' => null, 'detail' => 'Sin certificados monitorizados'];
        }

        $expired = (int) ($ssl['expired'] ?? 0);
        $expiring = (int) ($ssl['expiring'] ?? 0);
        $total = max((int) ($ssl['total'] ?? 0), 1);

        if ($expired > 0) {
            return ['label' => 'Certificados SSL', 'score' => 0.0, 'detail' => "{$expired} certificado(s) vencido(s)"];
        }

        $score = max(0, 100 - ($expiring / $total) * 60);

        return [
            'label' => 'Certificados SSL',
            'score' => round($score, 1),
            'detail' => $expiring > 0 ? "{$expiring} certificado(s) próximos a vencer" : 'Todos los certificados vigentes',
        ];
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function updatesComponent(array $updates): array
    {
        if (! ($updates['available'] ?? false)) {
            return ['label' => 'Actualizaciones', 'score' => null, 'detail' => 'Sin información de actualizaciones'];
        }

        $servers = (int) ($updates['servers_with_updates'] ?? 0);
        $security = (int) ($updates['total_security_updates'] ?? 0);

        return [
            'label' => 'Actualizaciones',
            'score' => max(0, 100 - $security * 8),
            'detail' => $servers > 0
                ? "{$servers} servidor(es) con {$security} actualizaciones de seguridad pendientes"
                : 'Sin actualizaciones de seguridad pendientes',
        ];
    }

    /**
     * @param  array<string, mixed>  $incidents
     * @return array<string, mixed>
     */
    private function incidentComponent(array $incidents): array
    {
        $critical = (int) ($incidents['critical_open'] ?? 0);
        $warning = (int) ($incidents['warning_open'] ?? 0);

        return [
            'label' => 'Incidentes',
            'score' => max(0, 100 - $critical * 25 - $warning * 8),
            'detail' => $critical + $warning > 0
                ? "{$critical} incidente(s) crítico(s) y {$warning} advertencia(s) abiertas"
                : 'Sin incidentes abiertos',
        ];
    }

    /**
     * @param  array<string, int>  $bands
     */
    private function band(int $score, array $bands): string
    {
        return match (true) {
            $score >= ($bands['excellent'] ?? 90) => 'excellent',
            $score >= ($bands['good'] ?? 75) => 'good',
            $score >= ($bands['attention'] ?? 60) => 'attention',
            $score >= ($bands['risk'] ?? 40) => 'risk',
            default => 'critical',
        };
    }

    private function bandLabel(string $band): string
    {
        return match ($band) {
            'excellent' => 'Excelente',
            'good' => 'Bueno',
            'attention' => 'Requiere atención',
            'risk' => 'En riesgo',
            default => 'Crítico',
        };
    }
}
