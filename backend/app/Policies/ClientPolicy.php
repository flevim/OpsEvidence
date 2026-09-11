<?php

namespace App\Policies;

use App\Domain\Enums\UserRole;

class ClientPolicy extends AccountScopedPolicy
{
    protected function writeRole(): UserRole
    {
        return UserRole::Technician;
    }

    protected function deleteRole(): UserRole
    {
        return UserRole::Admin;
    }
}
