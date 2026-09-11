<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\IncidentStatus;
use App\Http\Controllers\Api\Concerns\HandlesListQuery;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncidentController extends Controller
{
    use HandlesListQuery;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Incident::class);

        $query = Incident::query()
            ->with(['asset:id,name,type', 'client:id,name'])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')->toString()))
            ->when($request->filled('rule_key'), fn ($q) => $q->where('rule_key', $request->string('rule_key')->toString()))
            ->when($request->boolean('active'), fn ($q) => $q->active())
            ->when($request->filled('from'), fn ($q) => $q->where('opened_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('opened_at', '<=', $request->date('to')));

        $query = $this->applySorting($query, $request, ['opened_at', 'severity', 'status', 'created_at'], 'opened_at');

        return $this->paginated($query->paginate($this->perPage($request)));
    }

    public function show(Incident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        return response()->json(
            $incident->load(['asset:id,name,type', 'client:id,name', 'evidence', 'acknowledgedBy:id,name', 'resolvedBy:id,name']),
        );
    }

    public function update(Request $request, Incident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $data = $request->validate([
            'status' => ['required', Rule::in(IncidentStatus::values())],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $userId = $request->user()->id;
        $status = IncidentStatus::from($data['status']);

        match ($status) {
            IncidentStatus::Open => $incident->forceFill([
                'status' => IncidentStatus::Open,
                'acknowledged_at' => null,
                'acknowledged_by' => null,
                'resolved_at' => null,
                'resolved_by' => null,
            ])->save(),
            IncidentStatus::Acknowledged => $incident->acknowledge($userId),
            IncidentStatus::Resolved => $incident->resolve($userId, $data['note'] ?? null),
            IncidentStatus::Ignored => $incident->ignore($userId, $data['note'] ?? null),
        };

        AuditLog::record(
            event: 'incident.'.$status->value,
            subject: $incident,
            changes: ['status' => $status->value, 'note' => $data['note'] ?? null],
            userId: $userId,
            ip: $request->ip(),
        );

        return response()->json($incident->fresh()->load(['asset:id,name', 'client:id,name']));
    }
}
