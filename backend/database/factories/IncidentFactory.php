<?php

namespace Database\Factories;

use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\IncidentStatus;
use App\Domain\Enums\RuleKey;
use App\Models\Asset;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rule = RuleKey::DiskUsage;

        return [
            'asset_id' => null,
            'client_id' => fn (array $attributes) => $attributes['asset_id'] !== null
                ? Asset::withoutGlobalScopes()->find($attributes['asset_id'])?->client_id
                : \App\Models\Client::factory()->create()->id,
            'account_id' => fn (array $attributes) => $attributes['asset_id'] !== null
                ? Asset::withoutGlobalScopes()->find($attributes['asset_id'])?->account_id
                : \App\Models\Client::withoutGlobalScopes()->find($attributes['client_id'])?->account_id,
            'rule_key' => $rule->value,
            'signature' => fn (array $attributes) => $rule->value.':'.($attributes['asset_id'] ?? 'global').':'.fake()->unique()->numberBetween(1, 1000000),
            'severity' => IncidentSeverity::Warning->value,
            'status' => IncidentStatus::Open->value,
            'title' => 'Uso de disco elevado',
            'description' => 'El disco superó el umbral configurado.',
            'evidence_id' => null,
            'opened_at' => now()->subHour(),
            'metadata' => ['recommendation' => 'Liberar espacio.'],
        ];
    }

    public function critical(): self
    {
        return $this->state(fn (): array => ['severity' => IncidentSeverity::Critical->value]);
    }

    public function resolved(): self
    {
        return $this->state(fn (): array => [
            'status' => IncidentStatus::Resolved->value,
            'resolved_at' => now(),
        ]);
    }
}
