<?php

namespace App\Services\Dashboard;

use App\Domain\Enums\AssetType;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\IncidentSeverity;
use App\Models\Asset;
use App\Models\Check;
use App\Models\Client;
use App\Models\Evidence;
use App\Models\Incident;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos del panel general y del panel por cliente.
 *
 * Trabaja sobre la ultima evidencia de cada activo y tipo, no sobre el
 * historico completo: el panel responde "como esta todo ahora".
 */
class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(int $accountId): array
    {
        $clients = Client::query()->where('active', true)->get();
        $checks = Check::query()->where('enabled', true)->get();

        $latest = $this->latestEvidenceForAccount($accountId);

        $byStatus = $this->countByStatus($latest);
        $neverCollected = $checks->filter(fn (Check $check): bool => ! $check->hasData())->count();

        $openIncidents = Incident::query()->active()->get();

        return [
            'totals' => [
                'clients' => $clients->count(),
                'assets' => Asset::query()->where('active', true)->count(),
                'checks' => $checks->count(),
                'healthy' => $byStatus[EvidenceStatus::Healthy->value] ?? 0,
                'warning' => $byStatus[EvidenceStatus::Warning->value] ?? 0,
                'critical' => $byStatus[EvidenceStatus::Critical->value] ?? 0,
                'failed' => $byStatus[EvidenceStatus::Failed->value] ?? 0,
                'never_collected' => $neverCollected,
                'open_incidents' => $openIncidents->count(),
                'critical_incidents' => $openIncidents->where('severity', IncidentSeverity::Critical)->count(),
                'failed_backups' => $this->countByTypeAndStatus($latest, CheckType::BackupStatus, EvidenceStatus::Critical),
                'expiring_certificates' => $this->countByTypeAndStatus($latest, CheckType::SslExpiration, EvidenceStatus::Warning),
                'pending_updates' => $this->serversWithPendingUpdates($latest),
            ],
            'issues' => $this->issues($openIncidents, $accountId),
            'clients' => $this->clientSummaries($clients, $latest, $openIncidents),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clientOverview(Client $client): array
    {
        $assets = Asset::query()->where('client_id', $client->id)->where('active', true)->get();
        $checks = Check::query()->where('client_id', $client->id)->get();
        $latest = $this->latestEvidenceForClient($client->id);

        $byStatus = $this->countByStatus($latest);
        $openIncidents = Incident::query()->where('client_id', $client->id)->active()->get();

        return [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'slug' => $client->slug,
            ],
            'totals' => [
                'assets' => $assets->count(),
                'servers' => $assets->where('type', AssetType::Server)->count(),
                'applications' => $assets->where('type', AssetType::Application)->count(),
                'websites' => $assets->where('type', AssetType::Website)->count(),
                'checks' => $checks->count(),
                'healthy' => $byStatus[EvidenceStatus::Healthy->value] ?? 0,
                'warning' => $byStatus[EvidenceStatus::Warning->value] ?? 0,
                'critical' => $byStatus[EvidenceStatus::Critical->value] ?? 0,
                'failed' => $byStatus[EvidenceStatus::Failed->value] ?? 0,
                'never_collected' => $checks->filter(fn (Check $check): bool => ! $check->hasData())->count(),
                'open_incidents' => $openIncidents->count(),
                'failed_backups' => $this->countByTypeAndStatus($latest, CheckType::BackupStatus, EvidenceStatus::Critical),
                'expiring_certificates' => $this->countByTypeAndStatus($latest, CheckType::SslExpiration, EvidenceStatus::Warning),
                'pending_updates' => $this->serversWithPendingUpdates($latest),
                'containers_running' => $this->containers($latest)['running'],
                'containers_total' => $this->containers($latest)['total'],
            ],
            'issues' => $this->issues($openIncidents, $client->account_id),
            'evidence' => $latest->take(20)->values()->map(fn (Evidence $evidence): array => [
                'id' => $evidence->id,
                'type' => $evidence->type->value,
                'type_label' => $evidence->type->label(),
                'status' => $evidence->status->value,
                'status_label' => $evidence->status->label(),
                'title' => $evidence->title,
                'asset' => $evidence->asset?->name,
                'collected_at' => $evidence->collected_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return Collection<int, Evidence>
     */
    private function latestEvidenceForAccount(int $accountId): Collection
    {
        return $this->latestEvidence('account_id', $accountId);
    }

    /**
     * @return Collection<int, Evidence>
     */
    private function latestEvidenceForClient(int $clientId): Collection
    {
        return $this->latestEvidence('client_id', $clientId)->sortByDesc(
            fn (Evidence $evidence): int => $evidence->status->weight(),
        )->values();
    }

    /**
     * Ultima evidencia de cada activo y tipo, en una sola pasada.
     *
     * @return Collection<int, Evidence>
     */
    private function latestEvidence(string $column, int $value): Collection
    {
        $rows = DB::select(
            "SELECT DISTINCT ON (asset_id, type) *
             FROM evidence
             WHERE {$column} = ? AND collected_at >= ?
             ORDER BY asset_id, type, collected_at DESC, id DESC",
            [$value, CarbonImmutable::now()->subDays(30)],
        );

        return Evidence::hydrate(array_map(static fn ($row) => (array) $row, $rows))
            ->load('asset:id,name,type');
    }

    /**
     * @param  Collection<int, Evidence>  $latest
     * @return array<string, int>
     */
    private function countByStatus(Collection $latest): array
    {
        $counts = [];

        foreach (EvidenceStatus::cases() as $status) {
            $counts[$status->value] = $latest->where('status', $status)->count();
        }

        return $counts;
    }

    /**
     * @param  Collection<int, Evidence>  $latest
     */
    private function countByTypeAndStatus(Collection $latest, CheckType $type, EvidenceStatus $status): int
    {
        return $latest
            ->where('type', $type)
            ->filter(fn (Evidence $evidence): bool => $evidence->status->weight() >= $status->weight())
            ->count();
    }

    /**
     * @param  Collection<int, Evidence>  $latest
     */
    private function serversWithPendingUpdates(Collection $latest): int
    {
        return $latest
            ->where('type', CheckType::PendingUpdates)
            ->filter(fn (Evidence $evidence): bool => ((int) ($evidence->data['security_count'] ?? 0)) > 0)
            ->count();
    }

    /**
     * @param  Collection<int, Evidence>  $latest
     * @return array{running: int, total: int}
     */
    private function containers(Collection $latest): array
    {
        $evidence = $latest->where('type', CheckType::DockerContainerStatus)->first();

        if ($evidence === null) {
            return ['running' => 0, 'total' => 0];
        }

        $containers = $evidence->data['containers'] ?? [];

        return [
            'running' => count(array_filter($containers, fn (array $c): bool => mb_strtolower((string) ($c['state'] ?? '')) === 'running')),
            'total' => count($containers),
        ];
    }

    /**
     * @param  Collection<int, Incident>  $incidents
     * @return array<int, array<string, mixed>>
     */
    private function issues(Collection $incidents, int $accountId): array
    {
        $limit = (int) config('opsevidence.dashboard.issue_limit');

        return $incidents
            ->sortByDesc(fn (Incident $incident): int => $incident->severity->weight())
            ->take($limit)
            ->map(fn (Incident $incident): array => [
                'id' => $incident->id,
                'title' => $incident->title,
                'severity' => $incident->severity->value,
                'severity_label' => $incident->severity->label(),
                'status' => $incident->status->value,
                'status_label' => $incident->status->label(),
                'rule_key' => $incident->rule_key->value,
                'client_id' => $incident->client_id,
                'asset_id' => $incident->asset_id,
                'opened_at' => $incident->opened_at->toIso8601String(),
                'recommendation' => $incident->metadata['recommendation'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, Evidence>  $latest
     * @param  Collection<int, Incident>  $incidents
     * @return array<int, array<string, mixed>>
     */
    private function clientSummaries(Collection $clients, Collection $latest, Collection $incidents): array
    {
        return $clients->map(function (Client $client) use ($latest, $incidents): array {
            $clientEvidence = $latest->where('client_id', $client->id);
            $clientIncidents = $incidents->where('client_id', $client->id);

            $critical = $clientEvidence->where('status', EvidenceStatus::Critical)->count();
            $warning = $clientEvidence->where('status', EvidenceStatus::Warning)->count();

            return [
                'id' => $client->id,
                'name' => $client->name,
                'slug' => $client->slug,
                'critical' => $critical,
                'warning' => $warning,
                'healthy' => $clientEvidence->where('status', EvidenceStatus::Healthy)->count(),
                'open_incidents' => $clientIncidents->count(),
                'worst_status' => match (true) {
                    $critical > 0 => EvidenceStatus::Critical->value,
                    $warning > 0 => EvidenceStatus::Warning->value,
                    $clientEvidence->isEmpty() => EvidenceStatus::Unknown->value,
                    default => EvidenceStatus::Healthy->value,
                },
                'without_data' => $clientEvidence->isEmpty(),
            ];
        })->values()->all();
    }
}
