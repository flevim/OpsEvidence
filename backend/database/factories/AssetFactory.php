<?php

namespace Database\Factories;

use App\Domain\Enums\AssetType;
use App\Models\Asset;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'account_id' => fn (array $attributes) => Client::withoutGlobalScopes()
                ->find($attributes['client_id'])?->account_id,
            'environment_id' => null,
            'name' => fake()->unique()->slug(2),
            'type' => AssetType::Server->value,
            'hostname' => fake()->domainName(),
            'address' => fake()->ipv4(),
            'provider' => null,
            'metadata' => null,
            'active' => true,
        ];
    }

    public function ofType(AssetType $type): self
    {
        return $this->state(fn (): array => ['type' => $type->value]);
    }

    public function forClient(Client $client): self
    {
        return $this->state(fn (): array => [
            'client_id' => $client->id,
            'account_id' => $client->account_id,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
