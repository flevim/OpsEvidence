<?php

namespace App\Services\Rules\Contracts;

use App\Domain\Enums\RuleKey;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

interface Rule
{
    public function key(): RuleKey;

    /**
     * Devuelve el incumplimiento detectado o null si no aplica.
     * Nunca escribe en base de datos.
     */
    public function evaluate(RuleContext $context): ?RuleViolation;
}
