<?php

namespace App\Policies;

use App\Domain\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->atLeast(UserRole::Technician);
    }

    public function view(User $user, User $target): bool
    {
        return $this->sameAccount($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role->canManageUsers();
    }

    public function update(User $user, User $target): bool
    {
        if (! $user->role->canManageUsers() || ! $this->sameAccount($user, $target)) {
            return false;
        }

        // Un admin no puede tocar a un owner; solo un owner gestiona owners.
        if ($target->isOwner() && ! $user->isOwner()) {
            return false;
        }

        return true;
    }

    /**
     * El ultimo owner de una cuenta no puede eliminarse ni degradarse.
     */
    public function delete(User $user, User $target): bool
    {
        if (! $user->role->canManageUsers() || ! $this->sameAccount($user, $target)) {
            return false;
        }

        if ($target->isOwner()) {
            return $user->isOwner() && ! $this->isLastOwner($target);
        }

        return true;
    }

    private function isLastOwner(User $target): bool
    {
        return User::withoutGlobalScopes()
            ->where('account_id', $target->account_id)
            ->where('role', UserRole::Owner->value)
            ->where('is_active', true)
            ->count() <= 1;
    }

    private function sameAccount(User $user, User $target): bool
    {
        return $user->is_active && (int) $user->account_id === (int) $target->account_id;
    }
}
