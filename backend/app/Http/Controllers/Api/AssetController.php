<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\AssetType;
use App\Http\Controllers\Api\Concerns\HandlesListQuery;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    use HandlesListQuery;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $query = Asset::query()
            ->with(['client:id,name'])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $inner->where('name', 'ilike', $term)
                    ->orWhere('hostname', 'ilike', $term)
                    ->orWhere('address', 'ilike', $term);
            }));

        $query = $this->applySorting($query, $request, ['name', 'type', 'created_at', 'last_evidence_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)));
    }

    public function indexForClient(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $query = $client->assets()
            ->with(['environment:id,name,type'])
            ->withCount('checks')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')));

        $query = $this->applySorting($query, $request, ['name', 'type', 'created_at', 'last_evidence_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)));
    }

    public function storeForClient(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $asset = $client->assets()->create($this->validated($request, $client));

        AuditLog::record(
            event: 'asset.created',
            subject: $asset,
            changes: ['name' => $asset->name, 'type' => $asset->type->value],
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        return response()->json($asset->load('environment'), 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return response()->json(
            $asset->load(['client:id,name', 'environment:id,name,type'])->loadCount(['checks', 'incidents']),
        );
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $asset->update($this->validated($request, $asset->client, $asset));

        return response()->json($asset->fresh()->load('environment'));
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('delete', $asset);

        AuditLog::record(
            event: 'asset.deleted',
            subject: $asset,
            changes: ['name' => $asset->name],
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        $asset->delete();

        return response()->json(['message' => 'Activo archivado.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Client $client, ?Asset $asset = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('assets')->where('client_id', $client->id)->ignore($asset?->id),
            ],
            'type' => ['required', Rule::in(AssetType::values())],
            'environment_id' => [
                'nullable',
                Rule::exists('environments', 'id')->where('client_id', $client->id),
            ],
            'hostname' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:80'],
            'metadata' => ['nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if ($asset === null) {
            $data['account_id'] = $client->account_id;
        }

        return $data;
    }
}
