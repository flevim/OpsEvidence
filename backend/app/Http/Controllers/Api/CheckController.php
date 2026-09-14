<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\AssetType;
use App\Domain\Enums\CheckType;
use App\Http\Controllers\Controller;
use App\Jobs\RunCheckJob;
use App\Models\Asset;
use App\Models\Check;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckController extends Controller
{
    /**
     * Catálogo de tipos de check con los tipos de activo compatibles.
     *
     * Vive en el backend para que el panel no duplique la regla de
     * compatibilidad ni la lista de tipos.
     */
    public function types(): JsonResponse
    {
        $types = collect(CheckType::cases())->map(fn (CheckType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'asset_types' => array_map(
                static fn (AssetType $assetType): string => $assetType->value,
                $type->supportedAssetTypes(),
            ),
            'collected_by_platform' => $type->isCollectedByPlatform(),
            'default_interval_seconds' => $type->defaultIntervalSeconds(),
        ]);

        return response()->json(['data' => $types->values()]);
    }

    public function indexForAsset(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return response()->json([
            'data' => $asset->checks()->orderBy('type')->get()->map($this->present(...)),
        ]);
    }

    public function storeForAsset(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $data = $request->validate([
            'type' => ['required', Rule::in(CheckType::values())],
            'name' => ['required', 'string', 'max:150'],
            'configuration' => ['nullable', 'array'],
            'interval_seconds' => ['sometimes', 'integer', 'min:60', 'max:86400'],
            'freshness_ttl_seconds' => ['sometimes', 'integer', 'min:60', 'max:604800'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $type = CheckType::from($data['type']);

        if (! $type->supportsAssetType($asset->type)) {
            throw ValidationException::withMessages([
                'type' => "El check {$type->label()} no aplica a un activo de tipo {$asset->type->label()}.",
            ]);
        }

        $minInterval = (int) config('opsevidence.limits.min_check_interval_seconds');
        $interval = max((int) ($data['interval_seconds'] ?? $type->defaultIntervalSeconds()), $minInterval);

        $check = $asset->checks()->create([
            'account_id' => $asset->account_id,
            'client_id' => $asset->client_id,
            'type' => $type->value,
            'name' => $data['name'],
            'configuration' => $data['configuration'] ?? null,
            'interval_seconds' => $interval,
            'freshness_ttl_seconds' => (int) ($data['freshness_ttl_seconds'] ?? max($type->defaultFreshnessTtlSeconds(), $interval * 3)),
            'enabled' => $data['enabled'] ?? true,
            'next_run_at' => now(),
        ]);

        return response()->json($this->present($check), 201);
    }

    public function show(Check $check): JsonResponse
    {
        $this->authorize('view', $check);

        return response()->json($this->present($check->load('asset:id,name,type')));
    }

    public function update(Request $request, Check $check): JsonResponse
    {
        $this->authorize('update', $check);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'configuration' => ['nullable', 'array'],
            'interval_seconds' => ['sometimes', 'integer', 'min:60', 'max:86400'],
            'freshness_ttl_seconds' => ['sometimes', 'integer', 'min:60', 'max:604800'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $check->update($data);

        return response()->json($this->present($check->fresh()));
    }

    public function destroy(Check $check): JsonResponse
    {
        $this->authorize('delete', $check);

        $check->delete();

        return response()->json(['message' => 'Check eliminado.']);
    }

    public function run(Check $check): JsonResponse
    {
        $this->authorize('update', $check);

        if (! $check->type->isCollectedByPlatform()) {
            throw ValidationException::withMessages([
                'type' => 'Este tipo de check se alimenta desde el agente o un webhook, no se ejecuta desde OpsEvidence.',
            ]);
        }

        RunCheckJob::dispatch($check->id);

        return response()->json(['message' => 'Comprobación en cola.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Check $check): array
    {
        return array_merge($check->toArray(), [
            'freshness' => $check->freshness(),
            'type_label' => $check->type->label(),
        ]);
    }
}
