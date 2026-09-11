<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\RuleKey;
use App\Http\Controllers\Controller;
use App\Models\RuleSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Umbrales configurables del motor de reglas.
 *
 * Los umbrales se resuelven por cascada: cliente > cuenta > valores por defecto.
 */
class RuleSettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RuleSetting::class);

        $accountId = $request->user()->account_id;
        $clientId = $request->filled('client_id') ? $request->integer('client_id') : null;

        $settings = RuleSetting::query()
            ->where('account_id', $accountId)
            ->when($clientId !== null, fn ($q) => $q->where('client_id', $clientId))
            ->get()
            ->keyBy(fn (RuleSetting $setting): string => $setting->rule_key->value);

        $rules = collect(RuleKey::cases())->map(fn (RuleKey $key): array => [
            'rule_key' => $key->value,
            'label' => $key->label(),
            'default_severity' => $key->defaultSeverity()->value,
            'default_thresholds' => $key->defaultThresholds(),
            'enabled' => RuleSetting::isEnabled($accountId, $clientId, $key),
            'thresholds' => RuleSetting::resolveThresholds($accountId, $clientId, $key),
            'customized' => $settings->has($key->value),
            'setting_id' => $settings->get($key->value)?->id,
        ]);

        return response()->json(['data' => $rules]);
    }

    public function update(Request $request, RuleSetting $ruleSetting): JsonResponse
    {
        $this->authorize('update', $ruleSetting);

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'thresholds' => ['nullable', 'array'],
            'severity' => ['nullable', Rule::in(['info', 'warning', 'critical'])],
        ]);

        $ruleSetting->update($data);

        return response()->json([
            'id' => $ruleSetting->id,
            'rule_key' => $ruleSetting->rule_key->value,
            'enabled' => $ruleSetting->enabled,
            'thresholds' => RuleSetting::resolveThresholds(
                $ruleSetting->account_id,
                $ruleSetting->client_id,
                $ruleSetting->rule_key,
            ),
        ]);
    }
}
