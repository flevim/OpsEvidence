<?php

namespace App\Models\Concerns;

use App\Models\Account;
use App\Support\AccountContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aislamiento de tenant: toda consulta de un modelo que use este trait
 * queda restringida al account activo en AccountContext.
 *
 * No sustituye a las policies: es defensa en profundidad
 * (ver docs/security.md, seccion "Aislamiento de tenants").
 */
trait BelongsToAccount
{
    public static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $builder): void {
            $accountId = AccountContext::current();

            if ($accountId !== null) {
                $builder->where(
                    $builder->getModel()->getTable().'.account_id',
                    $accountId,
                );
            }
        });

        static::creating(function (self $model): void {
            if ($model->account_id === null) {
                $model->account_id = AccountContext::current();
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
