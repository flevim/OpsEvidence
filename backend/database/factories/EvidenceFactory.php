<?php

namespace Database\Factories;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceSource;
use App\Domain\Enums\EvidenceStatus;
use App\Models\Asset;
use App\Models\Evidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evidence>
 */
class EvidenceFactory extends Factory
{
    protected $model = Evidence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = CheckType::DiskUsage;
        $collectedAt = now()->subMinutes(fake()->numberBetween(1, 60));

        return [
            'asset_id' => Asset::factory(),
            'client_id' => fn (array $attributes) => Asset::withoutGlobalScopes()
                ->find($attributes['asset_id'])?->client_id,
            'account_id' => fn (array $attributes) => Asset::withoutGlobalScopes()
                ->find($attributes['asset_id'])?->account_id,
            'check_id' => null,
            'check_run_id' => null,
            'type' => $type->value,
            'status' => EvidenceStatus::Healthy->value,
            'raw_status' => null,
            'title' => 'Uso de disco: 42,0 %',
            'value_text' => null,
            'value_numeric' => 42.0,
            'unit' => '%',
            'data' => ['mountpoint' => '/', 'used_percent' => 42.0],
            'raw_data' => null,
            'source' => EvidenceSource::Agent->value,
            'collected_at' => $collectedAt,
            'created_at' => $collectedAt,
            'dedup_key' => fn (array $attributes) => hash('sha256', implode('|', [
                $attributes['type'],
                $attributes['asset_id'],
                $collectedAt->getTimestamp(),
                fake()->unique()->uuid(),
            ])),
        ];
    }

    public function ofType(CheckType $type): self
    {
        return $this->state(fn (): array => [
            'type' => $type->value,
            'title' => $type->label(),
        ]);
    }

    public function withStatus(EvidenceStatus $status): self
    {
        return $this->state(fn (): array => ['status' => $status->value]);
    }

    public function forAsset(Asset $asset): self
    {
        return $this->state(fn (): array => [
            'asset_id' => $asset->id,
            'client_id' => $asset->client_id,
            'account_id' => $asset->account_id,
        ]);
    }

    public function collectedAt(\DateTimeInterface $moment): self
    {
        return $this->state(fn (): array => [
            'collected_at' => $moment,
            'created_at' => $moment,
        ]);
    }

    public function dedupKey(string $key): self
    {
        return $this->state(fn (): array => ['dedup_key' => $key]);
    }
}
