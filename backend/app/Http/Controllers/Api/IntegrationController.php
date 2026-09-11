<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\IntegrationType;
use App\Http\Controllers\Api\Concerns\HandlesListQuery;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Integration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    use HandlesListQuery;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Integration::class);

        $query = Integration::query()
            ->with(['client:id,name'])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()));

        $query = $this->applySorting($query, $request, ['name', 'type', 'created_at', 'last_sync_at'], 'name');

        $paginated = $query->paginate($this->perPage($request));
        $paginated->through(fn (Integration $integration): array => $this->present($integration));

        return $this->paginated($paginated);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Integration::class);

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'type' => ['required', Rule::in(IntegrationType::values())],
            'name' => ['required', 'string', 'max:120'],
            'configuration' => ['nullable', 'array'],
            'credentials' => ['nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $client = Client::findOrFail($data['client_id']);
        $this->authorize('view', $client);

        $integration = $client->integrations()->create([
            ...$data,
            'account_id' => $client->account_id,
        ]);

        AuditLog::record(
            event: 'integration.created',
            subject: $integration,
            changes: ['type' => $integration->type->value, 'name' => $integration->name],
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        return response()->json($this->present($integration), 201);
    }

    public function show(Integration $integration): JsonResponse
    {
        $this->authorize('view', $integration);

        return response()->json($this->present($integration->load('client:id,name')));
    }

    public function update(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('update', $integration);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'configuration' => ['nullable', 'array'],
            'credentials' => ['nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('credentials', $data) && $data['credentials'] === null) {
            unset($data['credentials']);
        }

        $integration->update($data);

        return response()->json($this->present($integration->fresh()));
    }

    public function destroy(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('delete', $integration);

        AuditLog::record(
            event: 'integration.deleted',
            subject: $integration,
            changes: ['type' => $integration->type->value, 'name' => $integration->name],
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        $integration->delete();

        return response()->json(['message' => 'Integración eliminada.']);
    }

    /**
     * Las credenciales NUNCA se serializan: solo se informa si existen
     * (ver docs/security.md, amenaza T7).
     *
     * @return array<string, mixed>
     */
    private function present(Integration $integration): array
    {
        return [
            ...$integration->toArray(),
            'has_credentials' => $integration->hasCredentials(),
            'type_label' => $integration->type->label(),
            'implemented' => $integration->type->isImplemented(),
        ];
    }
}
