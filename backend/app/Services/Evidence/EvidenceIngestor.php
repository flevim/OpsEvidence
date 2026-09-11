<?php

namespace App\Services\Evidence;

use App\Domain\Enums\EvidenceStatus;
use App\Models\Asset;
use App\Models\Check;
use App\Models\CheckRun;
use App\Models\Client;
use App\Models\Evidence;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Unico punto de entrada de evidencia al sistema.
 *
 * Todos los origenes (collectors, agente, webhooks, registro manual) pasan por
 * aqui, de modo que exista una sola validacion, una sola normalizacion y un
 * solo lugar donde aplicar idempotencia (ver docs/evidence-model.md).
 */
class EvidenceIngestor
{
    /**
     * @param  array<int, EvidencePayload>  $payloads
     * @return array<int, Evidence>
     */
    public function ingestForCheck(Check $check, array $payloads, ?CheckRun $run = null): array
    {
        $stored = [];

        foreach ($payloads as $payload) {
            $evidence = $this->persist(
                payload: $payload,
                accountId: $check->account_id,
                clientId: $check->client_id,
                assetId: $check->asset_id,
                checkId: $check->id,
                checkRunId: $run?->id,
            );

            if ($evidence !== null) {
                $stored[] = $evidence;
            }
        }

        if ($stored !== []) {
            $this->touchAsset($check->asset_id);
            $this->updateCheckState($check, $stored);
        } else {
            $this->touchAsset($check->asset_id);
        }

        return $stored;
    }

    /**
     * Ingesta sin check asociado: agente, webhook o registro manual.
     *
     * @param  array<int, EvidencePayload>  $payloads
     * @return array<int, Evidence>
     */
    public function ingestStandalone(Client $client, Asset $asset, array $payloads, ?Check $check = null): array
    {
        $stored = [];

        foreach ($payloads as $payload) {
            $evidence = $this->persist(
                payload: $payload,
                accountId: $client->account_id,
                clientId: $client->id,
                assetId: $asset->id,
                checkId: $check?->id,
                checkRunId: null,
            );

            if ($evidence !== null) {
                $stored[] = $evidence;
            }
        }

        if ($stored !== []) {
            $this->touchAsset($asset->id);
        }

        return $stored;
    }

    private function persist(
        EvidencePayload $payload,
        int $accountId,
        int $clientId,
        int $assetId,
        ?int $checkId,
        ?int $checkRunId,
    ): ?Evidence {
        $collectedAt = $payload->collectedAtOrNow();
        $dedupKey = Evidence::dedupKey($checkId, $payload->type, $collectedAt, $payload->discriminator);

        $attributes = [
            'account_id' => $accountId,
            'client_id' => $clientId,
            'asset_id' => $assetId,
            'check_id' => $checkId,
            'check_run_id' => $checkRunId,
            'type' => $payload->type->value,
            'status' => $payload->status->value,
            'raw_status' => $payload->rawStatus,
            'title' => $payload->title,
            'value_text' => $payload->valueText,
            'value_numeric' => $payload->valueNumeric,
            'unit' => $payload->unit,
            'data' => $payload->data !== null ? json_encode($payload->data) : null,
            'raw_data' => $payload->rawData !== null ? json_encode($payload->rawData) : null,
            'source' => $payload->source->value,
            'collected_at' => $collectedAt,
            'created_at' => now(),
            'dedup_key' => $dedupKey,
        ];

        // insertOrIgnore + indice unico sobre dedup_key: idempotente y libre de
        // carrera si dos workers procesan el mismo check a la vez.
        $inserted = DB::table('evidence')->insertOrIgnore($attributes);

        if ($inserted === 0) {
            return null;
        }

        return Evidence::withoutGlobalScopes()->where('dedup_key', $dedupKey)->first();
    }

    private function touchAsset(int $assetId): void
    {
        Asset::withoutGlobalScopes()
            ->where('id', $assetId)
            ->update(['last_evidence_at' => now()]);
    }

    /**
     * @param  array<int, Evidence>  $stored
     */
    private function updateCheckState(Check $check, array $stored): void
    {
        $worst = collect($stored)
            ->map(fn (Evidence $evidence): EvidenceStatus => $evidence->status)
            ->sortByDesc(fn (EvidenceStatus $status): int => $status->weight())
            ->first();

        if ($worst instanceof EvidenceStatus) {
            $check->markSuccess($worst);
        }
    }

    /**
     * @return Collection<int, Evidence>
     */
    public function latestForAsset(int $assetId, int $limit = 50): Collection
    {
        return Evidence::query()
            ->forAsset($assetId)
            ->latestFirst()
            ->limit($limit)
            ->get();
    }
}
