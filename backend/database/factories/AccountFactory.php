<?php

namespace Database\Factories;

use App\Domain\Enums\AccountPlan;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'plan' => AccountPlan::Msp->value,
            'status' => 'active',
            'client_limit' => AccountPlan::Msp->clientLimit(),
            'settings' => [],
        ];
    }

    public function freelancer(): self
    {
        return $this->state(fn (): array => [
            'plan' => AccountPlan::Freelancer->value,
            'client_limit' => AccountPlan::Freelancer->clientLimit(),
        ]);
    }
}
