<?php

namespace App\Services\Reporting;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\IncidentStatus;
use App\Models\Activity;
use App\Models\Asset;
use App\Models\Check;
use App\Models\Client;
use App\Models\Evidence;
use App\Models\Incident;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Metricas agregadas de un cliente para un periodo.
 *
 * Es la unica capa que consulta la evidencia cruda en volumen: los informes
 * leen de aqui y nunca recorren la tabla `evidence` desde la vista.
 */
class ReportMetricsCollector
{
    /**
     * @return array<string, mixed>
     */
    public function forPeriod(Client $client, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $assets = Asset::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('active', true)
            ->get();

        $assetsWithData = $this->assetsWithData($client->id, $from, $to);

        return [
            'period' => [
                'start' => $from->toDateString(),
                'end' => $to->toDateString(),
                'days' => (int) $from->diffInDays($to) + 1,
            ],
            'assets' => [
                'total' => $assets->count(),
                'with_data' => $assetsWithData,
                'without_data' => max($assets->count() - $assetsWithData, 0),
                'by_type' => $assets->groupBy(fn (Asset $asset): string => $asset->type->value)
                    ->map->count()
                    ->all(),
            ],
            'checks' => $this->checks($client->id, $from, $to),
            'availability' => $this->availability($client->id, $from, $to),
            'backups' => $this->backups($client->id, $from, $to),
            'containers' => $this->containers($client->id, $from, $to),
            'ssl' => $this->ssl($client->id, $from, $to),
            'updates' => $this->updates($client->id, $from, $to),
            'deployments' => $this->deployments($client->id, $from, $to),
            'incidents' => $this->incidents($client->id, $from, $to),
            'activities' => $this->activities($client->id, $from, $to),
        ];
    }

    private function assetsWithData(int $clientId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) DB::table('evidence')
            ->where('client_id', $clientId)
            ->whereBetween('collected_at', [$from, $to])
            ->distinct()
            ->count('asset_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function checks(int $clientId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('evidence')
            ->selectRaw('status, count(*) as total')
            ->where('client_id', $clientId)
            ->whereBetween('collected_at', [$from, $to])
            ->groupBy('status')
            ->pluck('total', 'status');

        $checks = Check::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->get();

        $byStatus = [];
        foreach (EvidenceStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        $withData = $checks->filter(fn (Check $check): bool => $check->hasData())->count();

        return [
            'configured' => $checks->count(),
            'with_data' => $withData,
            'without_data' => $checks->count() - $withData,
            'runs' => array_sum($byStatus),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Disponibilidad a partir de las comprobaciones HTTP: proporcion de
     * observaciones saludables sobre el total de observaciones realizadas.
     * Si no hay comprobaciones HTTP, se devuelve null (no un cero).
     *
     * @return array{percentage: float|null, samples: int, healthy: int, down: int}
     */
    private function availability(int $clientId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = DB::table('evidence')
            ->where('client_id', $clientId)
            ->where('type', CheckType::HttpStatus->value)
            ->whereBetween('collected_at', [$from, $to])
            ->selectRaw("count(*) as samples, count(*) filter (where status = 'HEALTHY') as healthy, count(*) filter (where status = 'CRITICAL') as down")
            ->first();

        $samples = (int) ($row->samples ?? 0);

        return [
            'percentage' => $samples > 0 ? round(((int) $row->healthy / $samples) * 100, 3) : null,
            'samples' => $samples,
            'healthy' => (int) ($row->healthy ?? 0),
            'down' => (int) ($row->down ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function backups(int $clientId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = DB::table('evidence')
            ->where('client_id', $clientId)
            ->where('type', CheckType::BackupStatus->value)
            ->whereBetween('collected_at', [$from, $to])
            ->selectRaw("count(*) as total, count(*) filter (where status = 'HEALTHY') as ok, count(*) filter (where status = 'WARNING') as warned, count(*) filter (where status = 'CRITICAL') as failed")
            ->first();

        $latest = Evidence::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->where('type', CheckType::BackupStatus->value)
            ->orderByDesc('collected_at')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'ok' => (int) ($row->ok ?? 0),
            'warning' => (int) ($row->warned ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'last_at' => $latest?->collected_at?->toIso8601String(),
            'last_status' => $latest?->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function containers(int $clientId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $evidence = Evidence::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->where('type', CheckType::DockerContainerStatus->value)
            ->whereBetween('collected_at', [$from, $to])
            ->orderByDesc('collected_at')
            ->first();

        if ($evidence === null) {
            return ['available' => false, 'running' => 0, 'total' => 0];
        }

        $containers = $evidence->data['containers'] ?? [];
        $running = count(array_filter(
            $containers,
            fn (array $c): bool => mb_strtolower((string) ($c['state'] ?? '')) === 'running',
        ));

        return [
            'available' => true,
            'running' => $running,
            'total' => count($containers),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ssl(int $clientId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Evidence::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->where('type', CheckType::SslExpiration->value)
            ->whereBetween('collected_at', [$from, $to])
            ->orderByDesc('collected_at')
            ->get()
            ->unique('asset_id');

        if ($rows->isEmpty()) {
            return ['available' => false, 'expiring' => 0, 'expired' => 0, 'min_days' => null, 'worst' => null];
        }

        $expiring = $rows->filter(fn (Evidence $e): bool => $e->status === EvidenceStatus::Warning)->count();
        $expired = $rows->filter(fn (Evidence $e): bool => $e->status === EvidenceStatus::Critical)->count();
        $minDays = (int) $rows->min(fn (Evidence $e): float => (float) ($e->value_numeric ?? 0));
        $worst = $rows->sortBy(fn (Evidence $e): float => (float) ($e->value_numeric ?? 0))->first();

        return [
            'available' => true,
            'expiring' => $expiring,
            'expired' => $expired,
            'min_days' => $minDays,
            'worst' => $worst?->data['host'] ?? null,
            'total' => $rows->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updates(int $clientId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Evidence::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->where('type', CheckType::PendingUpdates->value)
            ->whereBetween('collected_at', [$from, $to])
            ->orderByDesc('collected_at')
            ->get()
            ->unique('asset_id');

        $serversWithUpdates = $rows->filter(fn (Evidence $e): bool => ((int) ($e->data['security_count'] ?? 0)) > 0)->count();

        return [
            'available' => $rows->isNotEmpty(),
            'servers_with_updates' => $serversWithUpdates,
            'total_security_updates' => (int) $rows->sum(fn (Evidence $e): int => (int) ($e->data['security_count'] ?? 0)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deployments(int $clientId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Evidence::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->where('type', CheckType::GithubWorkflow->value)
            ->whereBetween('collected_at', [$from, $to])
            ->get();

        return [
            'available' => $rows->isNotEmpty(),
            'total' => $rows->count(),
            'successful' => $rows->filter(fn (Evidence $e): bool => $e->status === EvidenceStatus::Healthy)->count(),
            'failed' => $rows->filter(fn (Evidence $e): bool => $e->status === EvidenceStatus::Critical)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function incidents(int $clientId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $opened = Incident::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->whereBetween('opened_at', [$from, $to])
            ->get();

        $open = Incident::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->whereIn('status', [IncidentStatus::Open->value, IncidentStatus::Acknowledged->value])
            ->get();

        return [
            'opened_in_period' => $opened->count(),
            'resolved_in_period' => $opened->filter(fn (Incident $i): bool => $i->resolved_at !== null)->count(),
            'still_open' => $open->count(),
            'critical_open' => $open->filter(fn (Incident $i): bool => $i->severity === IncidentSeverity::Critical)->count(),
            'warning_open' => $open->filter(fn (Incident $i): bool => $i->severity === IncidentSeverity::Warning)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activities(int $clientId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $activities = Activity::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->whereBetween('performed_at', [$from, $to])
            ->get();

        return [
            'total' => $activities->count(),
            'by_type' => $activities->groupBy(fn (Activity $a): string => $a->type->value)
                ->map
                ->count()
                ->all(),
        ];
    }
}
