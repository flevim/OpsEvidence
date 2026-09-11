<?php

namespace App\Services\Reporting;

use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\IncidentStatus;
use App\Domain\Enums\ReportStatus;
use App\Models\Activity;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Evidence;
use App\Models\Incident;
use App\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\View;

/**
 * Construye y renderiza el informe mensual.
 *
 * El informe es un documento CONGELADO: todo lo que se muestra queda copiado en
 * `snapshot`, de modo que un informe enviado se pueda reproducir identico meses
 * despues aunque la evidencia cruda haya sido purgada por retencion.
 */
class ReportBuilder
{
    public function __construct(
        private readonly ReportMetricsCollector $collector,
        private readonly HealthScoreCalculator $healthScore,
    ) {
    }

    public function build(Client $client, CarbonImmutable $from, CarbonImmutable $to, ?int $userId = null): Report
    {
        $metrics = $this->collector->forPeriod($client, $from, $to);
        $health = $this->healthScore->calculate($metrics);

        $report = Report::withoutGlobalScopes()->updateOrCreate(
            [
                'client_id' => $client->id,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
            ],
            [
                'account_id' => $client->account_id,
                'status' => ReportStatus::Ready->value,
                'health_score' => $health['score'],
                'metrics' => $metrics,
                'summary' => $this->summary($client, $metrics, $health),
                'snapshot' => $this->snapshot($client, $from, $to, $health),
                'generated_by' => $userId,
            ],
        );

        return $report;
    }

    public function renderHtml(Report $report): string
    {
        return View::make('reports.monthly', [
            'report' => $report,
            'client' => $report->client,
            'account' => $report->account,
            'metrics' => $report->metrics ?? [],
            'summary' => $report->summary ?? [],
            'snapshot' => $report->snapshot ?? [],
            'generatedAt' => now(),
        ])->render();
    }

    /**
     * Titulares en lenguaje de negocio: es lo unico que el cliente final
     * probablemente lea (ver docs/product.md, seccion 5.1).
     *
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $health
     * @return array<int, array<string, mixed>>
     */
    private function summary(Client $client, array $metrics, array $health): array
    {
        $availability = $metrics['availability']['percentage'] ?? null;
        $backups = $metrics['backups'] ?? [];

        return [
            [
                'key' => 'health_score',
                'label' => 'Índice de salud de la infraestructura',
                'value' => $health['score'],
                'display' => $health['score'] !== null ? $health['score'].'/100' : 'Sin datos suficientes',
                'detail' => $health['band_label'],
                'tone' => $this->scoreTone($health['band']),
            ],
            [
                'key' => 'availability',
                'label' => 'Disponibilidad',
                'value' => $availability,
                'display' => $availability !== null ? number_format((float) $availability, 2, ',', '.').' %' : 'Sin datos',
                'detail' => $availability !== null
                    ? ($metrics['availability']['samples'].' comprobaciones realizadas')
                    : 'No hay comprobaciones HTTP configuradas para este cliente',
                'tone' => $availability === null ? 'neutral' : ($availability >= 99.9 ? 'success' : ($availability >= 99 ? 'warning' : 'error')),
            ],
            [
                'key' => 'backups',
                'label' => 'Backups correctos',
                'value' => $backups['ok'] ?? 0,
                'display' => ((int) ($backups['total'] ?? 0)) > 0
                    ? ($backups['ok'] ?? 0).'/'.($backups['total'] ?? 0)
                    : 'Sin datos',
                'detail' => ((int) ($backups['failed'] ?? 0)) > 0
                    ? (($backups['failed']).' backup(s) fallaron en el periodo')
                    : 'Todos los backups registrados finalizaron correctamente',
                'tone' => ((int) ($backups['total'] ?? 0)) === 0 ? 'neutral' : (((int) ($backups['failed'] ?? 0)) > 0 ? 'error' : 'success'),
            ],
            [
                'key' => 'incidents',
                'label' => 'Incidentes',
                'value' => $metrics['incidents']['opened_in_period'] ?? 0,
                'display' => (string) ($metrics['incidents']['opened_in_period'] ?? 0),
                'detail' => (($metrics['incidents']['still_open'] ?? 0) > 0)
                    ? (($metrics['incidents']['still_open']).' siguen abiertos al cierre del periodo')
                    : 'Ninguno quedó abierto al cierre del periodo',
                'tone' => ((int) ($metrics['incidents']['critical_open'] ?? 0)) > 0 ? 'error' : 'success',
            ],
            [
                'key' => 'certificates',
                'label' => 'Certificados por vencer',
                'value' => $metrics['ssl']['expiring'] ?? 0,
                'display' => ($metrics['ssl']['available'] ?? false)
                    ? (string) ($metrics['ssl']['expiring'] ?? 0)
                    : 'Sin datos',
                'detail' => ($metrics['ssl']['min_days'] ?? null) !== null
                    ? ('El más próximo vence en '.$metrics['ssl']['min_days'].' días')
                    : 'No hay certificados monitorizados',
                'tone' => ((int) ($metrics['ssl']['expired'] ?? 0)) > 0 ? 'error' : (((int) ($metrics['ssl']['expiring'] ?? 0)) > 0 ? 'warning' : 'success'),
            ],
            [
                'key' => 'updates',
                'label' => 'Servidores con actualizaciones pendientes',
                'value' => $metrics['updates']['servers_with_updates'] ?? 0,
                'display' => ($metrics['updates']['available'] ?? false)
                    ? (string) ($metrics['updates']['servers_with_updates'] ?? 0)
                    : 'Sin datos',
                'detail' => ($metrics['updates']['available'] ?? false)
                    ? (($metrics['updates']['total_security_updates'] ?? 0).' actualizaciones de seguridad acumuladas')
                    : 'El agente no reporta información de actualizaciones',
                'tone' => ((int) ($metrics['updates']['total_security_updates'] ?? 0)) > 0 ? 'warning' : 'success',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $health
     * @return array<string, mixed>
     */
    private function snapshot(Client $client, CarbonImmutable $from, CarbonImmutable $to, array $health): array
    {
        $assets = Asset::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('active', true)
            ->get();

        $latest = Evidence::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->whereBetween('collected_at', [$from, $to])
            ->orderByDesc('collected_at')
            ->get()
            ->groupBy('asset_id')
            ->map(fn ($group) => $group->sortByDesc(fn (Evidence $e): int => $e->status->weight())->first());

        $incidents = Incident::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->whereBetween('opened_at', [$from, $to])
            ->orderByDesc('opened_at')
            ->get();

        $activities = Activity::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->whereBetween('performed_at', [$from, $to])
            ->orderBy('performed_at')
            ->get();

        return [
            'client' => [
                'name' => $client->name,
                'contact_name' => $client->contact_name,
            ],
            'health' => $health,
            'assets' => $assets->map(function (Asset $asset) use ($latest): array {
                /** @var Evidence|null $evidence */
                $evidence = $latest->get($asset->id);

                return [
                    'name' => $asset->name,
                    'type' => $asset->type->value,
                    'type_label' => $asset->type->label(),
                    'status' => $evidence?->status->value,
                    'status_label' => $evidence?->status->label() ?? 'Sin datos',
                    'title' => $evidence?->title,
                    'collected_at' => $evidence?->collected_at?->toIso8601String(),
                ];
            })->values()->all(),
            'open_incidents' => $incidents
                ->filter(fn (Incident $i): bool => in_array($i->status, [IncidentStatus::Open, IncidentStatus::Acknowledged], true))
                ->map(fn (Incident $i): array => [
                    'title' => $i->title,
                    'severity' => $i->severity->value,
                    'severity_label' => $i->severity->label(),
                    'opened_at' => $i->opened_at->toIso8601String(),
                    'status' => $i->status->value,
                    'recommendation' => $i->metadata['recommendation'] ?? null,
                ])->values()->all(),
            'resolved_incidents' => $incidents
                ->filter(fn (Incident $i): bool => $i->resolved_at !== null)
                ->map(fn (Incident $i): array => [
                    'title' => $i->title,
                    'severity' => $i->severity->value,
                    'opened_at' => $i->opened_at->toIso8601String(),
                    'resolved_at' => $i->resolved_at->toIso8601String(),
                    'note' => $i->resolution_note,
                ])->values()->all(),
            'activities' => $activities->map(fn (Activity $a): array => [
                'type' => $a->type->value,
                'type_label' => $a->type->label(),
                'title' => $a->title,
                'description' => $a->description,
                'performed_at' => $a->performed_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function scoreTone(?string $band): string
    {
        return match ($band) {
            'excellent', 'good' => 'success',
            'attention' => 'warning',
            'risk', 'critical' => 'error',
            default => 'neutral',
        };
    }
}
