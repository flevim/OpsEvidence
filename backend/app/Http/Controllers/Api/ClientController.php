<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesListQuery;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    use HandlesListQuery;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $query = Client::query()
            ->withCount(['assets', 'checks'])
            ->when($request->filled('search'), fn ($q) => $q->where(
                'name', 'ilike', '%'.$request->string('search')->toString().'%',
            ))
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')));

        $query = $this->applySorting($query, $request, ['name', 'created_at', 'updated_at', 'active'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Client::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $account = $request->user()->account;

        if (! $account->canAddClient()) {
            throw ValidationException::withMessages([
                'name' => "El plan {$account->plan->label()} permite hasta {$account->client_limit} clientes.",
            ]);
        }

        $client = $account->clients()->create($data);

        AuditLog::record(
            event: 'client.created',
            subject: $client,
            changes: $data,
            accountId: $account->id,
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        return response()->json($client->loadCount(['assets', 'checks']), 201);
    }

    public function show(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        return response()->json(
            $client->loadCount(['assets', 'checks', 'incidents', 'reports']),
        );
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $client->update($data);

        AuditLog::record(
            event: 'client.updated',
            subject: $client,
            changes: $data,
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        return response()->json($client->fresh()->loadCount(['assets', 'checks']));
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        AuditLog::record(
            event: 'client.deleted',
            subject: $client,
            changes: ['name' => $client->name],
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        $client->delete();

        return response()->json(['message' => 'Cliente archivado.']);
    }
}
