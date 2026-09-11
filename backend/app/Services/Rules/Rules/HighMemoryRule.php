<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\RuleKey;

class HighMemoryRule extends ThresholdEvidenceRule
{
    public function key(): RuleKey
    {
        return RuleKey::HighMemory;
    }

    protected function checkType(): CheckType
    {
        return CheckType::MemoryUsage;
    }

    protected function warningKey(): string
    {
        return 'warning_percent';
    }

    protected function criticalKey(): string
    {
        return 'critical_percent';
    }

    protected function unit(): string
    {
        return '%';
    }

    protected function subject(): string
    {
        return 'Uso de memoria elevado';
    }

    protected function recommendation(float $percent): string
    {
        return 'Revisar los procesos que más memoria consumen y evaluar ampliar la memoria del servidor.';
    }
}
