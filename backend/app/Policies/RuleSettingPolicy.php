<?php

namespace App\Policies;

use App\Domain\Enums\UserRole;

class RuleSettingPolicy extends AccountScopedPolicy
{
    protected function writeRole(): UserRole
    {
        return UserRole::Admin;
    }

    protected function deleteRole(): UserRole
    {
        return UserRole::Admin;
    }
}
