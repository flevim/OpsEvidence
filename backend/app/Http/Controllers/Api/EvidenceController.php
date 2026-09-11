<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceSource;
use App\Domain\Enums\EvidenceStatus;
use App\Http\Controllers\Api\Concerns\HandlesListQuery;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Evidence;
use App\Services\Evidence\EvidenceIngestor;
use App\Services\Evidence\EvidenceNormalizer;
use App\Services\Evidence\EvidencePayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EvidenceController extends Controller
{
    use HandlesListQuery;

    public function __construct(private readonly EvidenceIngestor $ingestor) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Evidence::class);

        $query = Evidence::query()
            ->with(['asset:id,name,type'])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('asset_id'), fn ($q) => $q->where('asset_id', $request->integer('asset_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('from'), fn ($q) => $q->where('collected_at', '>=', CarbonImmutable::parse($request->string('from')->toString())))
            ->when($request->filled('to'), fn ($q) => $q->where('collected_at', '<=', CarbonImmutable::parse($request->string('to')->toString())));

        $query = $this->applySorting($query, $request, ['collected_at', 'created_at', 'status', 'type'], 'collected_at');

        return $this->paginated($query->paginate($this->perPage($request)));
    }

    public function indexForAsset(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        $query = $asset->evidence()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()));

        $query = $this->applySorting($query, $request, ['collected_at', 'status', 'type'], 'collected_at');

        return $this->paginated($query->paginate($this->perPage($request)));
    }

    /**
     * Registro manual de evidencia desde el panel.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Evidence::class);

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'type' => ['required', Rule::in(CheckType::values())],
            'status' => ['required', Rule::in(EvidenceStatus::values())],
            'title' => ['required', 'string', 'max:255'],
            'value_text' => ['nullable', 'string', 'max:255'],
            'value_numeric' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:30'],
            'data' => ['nullable', 'array'],
            'collected_at' => ['nullable', 'date'],
        ]);

        $client = Client::findOrFail($data['client_id']);
        $asset = Asset::findOrFail($data['asset_id']);

        $this->authorize('view', $client);
        $this->authorize('view', $asset);

        $payload = EvidencePayload::make(
            type: CheckType::from($data['type']),
            status: EvidenceStatus::from($data['status']),
            title: $data['title'],
            options: [
                'value_text' => $data['value_text'] ?? null,
                'value_numeric' => isset($data['value_numeric']) ? (float) $data['value_numeric'] : null,
                'unit' => $data['unit'] ?? null,
                'data' => $data['data'] ?? null,
                'source' => EvidenceSource::Manual,
                'collected_at' => isset($data['collected_at']) ? CarbonImmutable::parse($data['collected_at']) : null,
            ],
        );

        $stored = $this->ingestor->ingestStandalone($client, $asset, [$payload]);

        return response()->json([
            'stored' => count($stored),
            'evidence' => $stored[0] ?? null,
        ], 201);
    }

    /**
     * Registro de resultado de backup desde el panel (equivalente autenticado
     * del webhook, para el tecnico que lo ejecuta a mano).
     */
    public function storeBackup(Request $request): JsonResponse
    {
        $this->authorize('create', Evidence::class);

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'status' => ['required', 'string', 'max:40'],
            'size' => ['nullable', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $client = Client::findOrFail($data['client_id']);
        $asset = Asset::findOrFail($data['asset_id']);

        $this->authorize('view', $client);
        $this->authorize('view', $asset);

        $normalizer = app(EvidenceNormalizer::class);
        $status = $normalizer->fromBackupStatus($data['status']);

        $payload = EvidencePayload::make(
            type: CheckType::BackupStatus,
            status: $status,
            title: 'Backup registrado manualmente: '.$data['status'],
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
                'source' => EvidenceSource::Manual,
            ],
        );

        $stored = $this->ingestor->ingestStandalone($client, $asset, [$payload]);

        return response()->json(['stored' => count($stored)], 201);
    }
}
