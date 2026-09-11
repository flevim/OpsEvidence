<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\RuleKey;

class HighCpuRule extends ThresholdEvidenceRule
{
    public function key(): RuleKey
    {
        return RuleKey::HighCpu;
    }

    protected function checkType(): CheckType
    {
        return CheckType::CpuUsage;
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
        return 'Uso de CPU elevado';
    }

    protected function recommendation(float $percent): string
    {
        return 'Identificar el proceso que consume la CPU y evaluar optimizarlo o ampliar la capacidad del servidor.';
    }
}
