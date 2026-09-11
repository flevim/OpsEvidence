<?php

namespace App\Policies;

use App\Domain\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * La evidencia es de solo lectura: no existen update ni delete, y la
 * inmutabilidad esta garantizada por el propio modelo.
 */
class EvidencePolicy extends AccountScopedPolicy
{
    protected function writeRole(): UserRole
    {
        return UserRole::Technician;
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function restore(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }
}
