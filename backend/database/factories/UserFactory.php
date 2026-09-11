<?php

namespace Database\Factories;

use App\Domain\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => UserRole::Technician->value,
            'is_active' => true,
        ];
    }

    public function owner(): self
    {
        return $this->state(fn (): array => ['role' => UserRole::Owner->value]);
    }

    public function admin(): self
    {
        return $this->state(fn (): array => ['role' => UserRole::Admin->value]);
    }

    public function technician(): self
    {
        return $this->state(fn (): array => ['role' => UserRole::Technician->value]);
    }

    public function viewer(): self
    {
        return $this->state(fn (): array => ['role' => UserRole::Viewer->value]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function forAccount(Account $account, UserRole $role = UserRole::Owner): self
    {
        return $this->state(fn (): array => [
            'account_id' => $account->id,
            'role' => $role->value,
        ]);
    }
}
