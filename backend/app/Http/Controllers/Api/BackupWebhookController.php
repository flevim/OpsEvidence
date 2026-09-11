<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceSource;
use App\Http\Controllers\Controller;
use App\Jobs\EvaluateClientRulesJob;
use App\Models\ApiToken;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Evidence;
use App\Services\Evidence\EvidenceIngestor;
use App\Services\Evidence\EvidenceNormalizer;
use App\Services\Evidence\EvidencePayload;
use App\Support\AccountContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Webhook generico de backups.
 *
 * Una sola interfaz cubre restic, borg, pg_dump, Veeam o cualquier script que
 * el cliente ya tenga: basta un curl al final del backup
 * (ver ADR-006 y docs/integrations.md).
 */
class BackupWebhookController extends Controller
{
    public function __construct(
        private readonly EvidenceIngestor $ingestor,
        private readonly EvidenceNormalizer $normalizer,
    ) {
    }

    public function store(Request $request, string $token): JsonResponse
    {
        $apiToken = $this->resolveToken($request, $token);

        $data = $request->validate([
            'source' => ['required', 'string', 'max:150'],
            'status' => ['required', 'string', 'max:40'],
            'size' => ['nullable', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'message' => ['nullable', 'string', 'max:1000'],
            'timestamp' => ['nullable', 'date'],
        ]);

        $clientId = $apiToken->client_id;

        if ($clientId === null) {
            throw ValidationException::withMessages([
                'source' => 'El token de webhook debe estar acotado a un cliente.',
            ]);
        }

        return AccountContext::run($apiToken->account_id, function () use ($apiToken, $clientId, $data): JsonResponse {
            $client = Client::withoutGlobalScopes()->findOrFail($clientId);

            $asset = $this->resolveAsset($apiToken, $client, $data['source']);

            $status = $this->normalizer->fromBackupStatus($data['status']);

            $payload = EvidencePayload::make(
                type: CheckType::BackupStatus,
                status: $status,
                title: $this->title($asset, $data),
                options: [
                    'raw_status' => $data['status'],
                    'value_text' => $data['status'],
                    'value_numeric' => isset($data['size']) ? (float) $data['size'] : null,
                    'unit' => isset($data['size']) ? 'bytes' : null,
                    'data' => [
                        'duration_seconds' => $data['duration_seconds'] ?? null,
                        'size_bytes' => $data['size'] ?? null,
                        'message' => $data['message'] ?? null,
                    ],
                    'raw_data' => $data,
                    'source' => EvidenceSource::Webhook,
                    'collected_at' => isset($data['timestamp']) ? \Carbon\CarbonImmutable::parse($data['timestamp']) : null,
                ],
            );

            $stored = $this->ingestor->ingestStandalone($client, $asset, [$payload]);

            if ($stored !== []) {
                EvaluateClientRulesJob::dispatch($client->id);
            }

            return response()->json([
                'accepted' => count($stored),
                'skipped' => $stored === [] ? 1 : 0,
                'asset_id' => $asset->id,
            ], 202);
        });
    }

    private function resolveToken(Request $request, string $token): ApiToken
    {
        $apiToken = ApiToken::findByPlainText($token);

        if ($apiToken === null || ! $apiToken->isUsable()) {
            throw new AuthenticationException('Token de webhook inválido.');
        }

        if (! $apiToken->hasAbility(\App\Domain\Enums\TokenAbility::EvidenceWrite)) {
            throw new AuthenticationException('El token no puede registrar evidencia.');
        }

        $apiToken->registerUsage($request->ip());

        return $apiToken;
    }

    private function resolveAsset(ApiToken $token, Client $client, string $source): Asset
    {
        if ($token->asset_id !== null) {
            return Asset::withoutGlobalScopes()->findOrFail($token->asset_id);
        }

        $asset = Asset::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where(fn ($query) => $query->where('name', $source)->orWhere('hostname', $source))
            ->first();

        return $asset ?? throw ValidationException::withMessages([
            'source' => "No existe un activo llamado '{$source}' en el cliente {$client->name}.",
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function title(Asset $asset, array $data): string
    {
        $ok = $this->normalizer->fromBackupStatus($data['status'])->value === 'HEALTHY';
        $size = isset($data['size']) ? $this->humanBytes((int) $data['size']) : null;

        return sprintf(
            '%s de %s: %s%s',
            'Backup',
            $asset->name,
            $ok ? 'completado' : $data['status'],
            $size !== null ? " ({$size})" : '',
        );
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 1, ',', '.').' '.$units[$index];
    }
}
