<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\RuleKey;

class DiskUsageRule extends ThresholdEvidenceRule
{
    public function key(): RuleKey
    {
        return RuleKey::DiskUsage;
    }

    protected function checkType(): CheckType
    {
        return CheckType::DiskUsage;
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
        return 'Uso de disco elevado';
    }

    protected function recommendation(float $percent): string
    {
        return $percent >= 90
            ? 'Liberar espacio de inmediato: revisar logs, backups locales y archivos temporales, o ampliar el almacenamiento.'
            : 'Planificar una limpieza o ampliación de almacenamiento antes de que el disco se sature.';
    }
}
