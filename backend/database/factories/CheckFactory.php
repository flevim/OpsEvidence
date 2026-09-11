<?php

namespace Database\Factories;

use App\Domain\Enums\CheckType;
use App\Models\Asset;
use App\Models\Check;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Check>
 */
class CheckFactory extends Factory
{
    protected $model = Check::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = CheckType::DiskUsage;

        return [
            'asset_id' => Asset::factory(),
            'client_id' => fn (array $attributes) => Asset::withoutGlobalScopes()
                ->find($attributes['asset_id'])?->client_id,
            'account_id' => fn (array $attributes) => Asset::withoutGlobalScopes()
                ->find($attributes['asset_id'])?->account_id,
            'type' => $type->value,
            'name' => $type->label(),
            'configuration' => null,
            'interval_seconds' => 600,
            'freshness_ttl_seconds' => 1800,
            'enabled' => true,
            'consecutive_failures' => 0,
            'next_run_at' => now(),
        ];
    }

    public function ofType(CheckType $type): self
    {
        return $this->state(fn (): array => [
            'type' => $type->value,
            'name' => $type->label(),
        ]);
    }

    public function forAsset(Asset $asset): self
    {
        return $this->state(fn (): array => [
            'asset_id' => $asset->id,
            'client_id' => $asset->client_id,
            'account_id' => $asset->account_id,
        ]);
    }

    public function disabled(): self
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }

    public function withData(): self
    {
        return $this->state(fn (): array => [
            'last_run_at' => now()->subMinutes(2),
            'last_success_at' => now()->subMinutes(2),
            'last_status' => 'HEALTHY',
        ]);
    }
}
