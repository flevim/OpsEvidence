<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'name' => fake()->unique()->company(),
            'description' => fake()->sentence(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->unique()->companyEmail(),
            'active' => true,
        ];
    }

    public function forAccount(Account $account): self
    {
        return $this->state(fn (): array => ['account_id' => $account->id]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
