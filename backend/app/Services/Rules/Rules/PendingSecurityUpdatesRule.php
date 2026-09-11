<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

class PendingSecurityUpdatesRule implements Rule
{
    public function key(): RuleKey
    {
        return RuleKey::PendingSecurityUpdates;
    }

    public function evaluate(RuleContext $context): ?RuleViolation
    {
        $thresholds = $context->thresholds($this->key());
        $criticalCount = (int) ($thresholds['critical_count'] ?? 10);

        foreach ($context->evidenceOfType(CheckType::PendingUpdates) as $evidence) {
            $securityCount = (int) ($evidence->data['security_count'] ?? 0);
            $totalCount = (int) ($evidence->data['total_count'] ?? $securityCount);

            if ($securityCount <= 0) {
                continue;
            }

            $assetName = $context->asset($evidence->asset_id)?->name ?? 'el servidor';

            return new RuleViolation(
                rule: $this->key(),
                severity: $securityCount >= $criticalCount
                    ? IncidentSeverity::Critical
                    : IncidentSeverity::Warning,
                title: sprintf('%s tiene %d actualizaciones de seguridad pendientes', $assetName, $securityCount),
                description: sprintf('El servidor acumula %d actualizaciones pendientes (%d de seguridad).', $totalCount, $securityCount),
                assetId: $evidence->asset_id,
                evidenceId: $evidence->id,
                recommendation: 'Programar una ventana de mantenimiento para aplicar las actualizaciones de seguridad.',
            );
        }

        return null;
    }
}
