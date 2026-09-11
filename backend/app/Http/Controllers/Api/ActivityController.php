<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\ActivityType;
use App\Http\Controllers\Api\Concerns\HandlesListQuery;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    use HandlesListQuery;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $query = Activity::query()
            ->with(['asset:id,name', 'client:id,name', 'user:id,name'])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('asset_id'), fn ($q) => $q->where('asset_id', $request->integer('asset_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('from'), fn ($q) => $q->where('performed_at', '>=', CarbonImmutable::parse($request->string('from')->toString())))
            ->when($request->filled('to'), fn ($q) => $q->where('performed_at', '<=', CarbonImmutable::parse($request->string('to')->toString())));

        $query = $this->applySorting($query, $request, ['performed_at', 'created_at', 'type'], 'performed_at');

        return $this->paginated($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Activity::class);

        $data = $this->validated($request);

        $client = Client::findOrFail($data['client_id']);
        $this->authorize('view', $client);

        $asset = isset($data['asset_id']) ? Asset::findOrFail($data['asset_id']) : null;

        if ($asset !== null) {
            $this->authorize('view', $asset);
        }

        $activity = $client->activities()->create([
            ...$data,
            'account_id' => $client->account_id,
            'user_id' => $request->user()->id,
        ]);

        AuditLog::record(
            event: 'activity.created',
            subject: $activity,
            changes: ['type' => $activity->type->value, 'title' => $activity->title],
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        return response()->json($activity->load(['asset:id,name', 'user:id,name']), 201);
    }

    public function show(Activity $activity): JsonResponse
    {
        $this->authorize('view', $activity);

        return response()->json($activity->load(['asset:id,name', 'client:id,name', 'user:id,name']));
    }

    public function update(Request $request, Activity $activity): JsonResponse
    {
        $this->authorize('update', $activity);

        $activity->update($this->validated($request, $activity));

        return response()->json($activity->fresh()->load(['asset:id,name', 'user:id,name']));
    }

    public function destroy(Activity $activity): JsonResponse
    {
        $this->authorize('delete', $activity);

        $activity->delete();

        return response()->json(['message' => 'Actividad eliminada.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Activity $activity = null): array
    {
        return $request->validate([
            'client_id' => [$activity === null ? 'required' : 'sometimes', 'integer', 'exists:clients,id'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'type' => ['required', Rule::in(ActivityType::values())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'performed_at' => ['required', 'date'],
            'billable' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
