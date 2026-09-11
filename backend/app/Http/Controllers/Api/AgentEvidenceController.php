<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\CheckType;
use App\Http\Controllers\Controller;
use App\Jobs\EvaluateClientRulesJob;
use App\Models\ApiToken;
use App\Models\Asset;
use App\Models\Check;
use App\Models\Client;
use App\Models\Evidence;
use App\Services\Agent\AgentEvidenceNormalizer;
use App\Services\Agent\AgentTokenAuthenticator;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\AccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgentEvidenceController extends Controller
{
    public function __construct(
        private readonly AgentTokenAuthenticator $authenticator,
        private readonly AgentEvidenceNormalizer $normalizer,
        private readonly EvidenceIngestor $ingestor,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $token = $this->authenticator->authenticate($request);

        $maxItems = (int) config('opsevidence.limits.agent_evidence_max_items');

        $data = $request->validate([
            'asset' => ['nullable', 'string', 'max:150'],
            'client' => ['nullable', 'string', 'max:150'],
            'agent_version' => ['nullable', 'string', 'max:40'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'collected_at' => ['nullable', 'date'],
            'evidence' => ['required', 'array', 'min:1', 'max:'.$maxItems],
            'evidence.*.type' => ['required', Rule::in(CheckType::values())],
            'evidence.*.data' => ['nullable', 'array'],
            'evidence.*.value_numeric' => ['nullable', 'numeric'],
            'evidence.*.value_text' => ['nullable', 'string', 'max:255'],
            'evidence.*.unit' => ['nullable', 'string', 'max:30'],
            'evidence.*.raw_status' => ['nullable', 'string', 'max:120'],
            'evidence.*.title' => ['nullable', 'string', 'max:255'],
        ]);

        $collectedAt = isset($data['collected_at'])
            ? CarbonImmutable::parse($data['collected_at'])
            : CarbonImmutable::now();

        return AccountContext::run($token->account_id, function () use ($token, $data, $collectedAt): JsonResponse {
            $client = $this->resolveClient($token, $data);
            $asset = $this->resolveAsset($token, $client, $data);

            $payloads = [];
            $checks = Check::withoutGlobalScopes()
                ->where('asset_id', $asset->id)
                ->get()
                ->keyBy(fn (Check $check): string => $check->type->value);

            foreach ($data['evidence'] as $item) {
                $type = CheckType::from($item['type']);
                $itemData = $item['data'] ?? [];

                if (isset($item['value_numeric'])) {
                    $itemData['value_numeric'] = $item['value_numeric'];
                }

                $payloads[] = $this->normalizer->normalize(
                    type: $type,
                    data: $itemData,
                    check: $checks->get($type->value),
                    rawStatus: $item['raw_status'] ?? null,
                    title: $item['title'] ?? null,
                    collectedAt: $collectedAt,
                );
            }

            $stored = $this->ingestor->ingestStandalone($client, $asset, $payloads);

            $this->updateMatchingChecks($checks, $stored, $collectedAt);

            if ($stored !== []) {
                EvaluateClientRulesJob::dispatch($client->id);
            }

            return response()->json([
                'accepted' => count($stored),
                'skipped' => count($payloads) - count($stored),
                'asset_id' => $asset->id,
            ], 202);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveClient(ApiToken $token, array $data): Client
    {
        if ($token->client_id !== null) {
            return Client::withoutGlobalScopes()->findOrFail($token->client_id);
        }

        $reference = $data['client'] ?? null;

        if (blank($reference)) {
            throw ValidationException::withMessages([
                'client' => 'El token no está acotado a un cliente: indica "client" en la petición.',
            ]);
        }

        $client = Client::withoutGlobalScopes()
            ->where('account_id', $token->account_id)
            ->where(fn ($query) => $query->where('slug', $reference)->orWhere('name', $reference))
            ->first();

        return $client ?? throw ValidationException::withMessages([
            'client' => "No se encontró el cliente '{$reference}'.",
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveAsset(ApiToken $token, Client $client, array $data): Asset
    {
        if ($token->asset_id !== null) {
            return Asset::withoutGlobalScopes()->findOrFail($token->asset_id);
        }

        $reference = $data['asset'] ?? $data['hostname'] ?? null;

        if (blank($reference)) {
            throw ValidationException::withMessages([
                'asset' => 'El token no está acotado a un activo: indica "asset" en la petición.',
            ]);
        }

        $asset = Asset::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where(fn ($query) => $query->where('name', $reference)
                ->orWhere('hostname', $reference)
                ->orWhere('id', is_numeric($reference) ? (int) $reference : 0))
            ->first();

        return $asset ?? throw ValidationException::withMessages([
            'asset' => "No se encontró el activo '{$reference}' en el cliente {$client->name}.",
        ]);
    }

    /**
     * @param  Collection<string, Check>  $checks
     * @param  array<int, Evidence>  $stored
     */
    private function updateMatchingChecks(Collection $checks, array $stored, CarbonImmutable $collectedAt): void
    {
        foreach ($stored as $evidence) {
            $check = $checks->get($evidence->type->value);

            if ($check !== null) {
                $check->markSuccess($evidence->status, $collectedAt);
            }
        }
    }
}
