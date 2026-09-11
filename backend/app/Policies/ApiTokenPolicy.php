<?php

namespace App\Policies;

use App\Domain\Enums\UserRole;
use App\Models\User;

/**
 * Los tokens de agente son credenciales: solo admin y owner los gestionan,
 * y un token nunca se puede leer una vez emitido.
 */
class ApiTokenPolicy extends AccountScopedPolicy
{
    protected function writeRole(): UserRole
    {
        return UserRole::Admin;
    }

    protected function deleteRole(): UserRole
    {
        return UserRole::Admin;
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role->canManageTokens();
    }
}
