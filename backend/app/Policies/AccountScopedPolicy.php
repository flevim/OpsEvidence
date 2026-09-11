<?php

namespace App\Policies;

use App\Domain\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de las policies de OpsEvidence.
 *
 * Dos condiciones, siempre juntas:
 *  1. El recurso pertenece al mismo account que el usuario.
 *  2. El rol del usuario alcanza para la accion.
 *
 * El usuario inactivo nunca autoriza, aunque su rol sea alto.
 */
abstract class AccountScopedPolicy
{
    /**
     * Rol minimo para crear y editar.
     */
    protected function writeRole(): UserRole
    {
        return UserRole::Technician;
    }

    /**
     * Rol minimo para eliminar.
     */
    protected function deleteRole(): UserRole
    {
        return UserRole::Admin;
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->atLeast($this->writeRole());
    }

    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model) && $user->atLeast($this->writeRole());
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->owns($user, $model) && $user->atLeast($this->deleteRole());
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->owns($user, $model) && $user->atLeast($this->deleteRole());
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->owns($user, $model) && $user->isOwner();
    }

    protected function owns(User $user, Model $model): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return (int) $user->account_id === (int) $model->getAttribute('account_id');
    }
}
