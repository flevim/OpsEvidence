<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;
use App\Models\Check;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

/**
 * La recolección misma está fallando.
 *
 * Es la regla más importante para la confianza en el producto: sin ella, un
 * collector roto produce informes vacíos sin que nadie se entere y el cliente
 * interpreta que no pasó nada.
 */
class CollectorFailingRule implements Rule
{
    public function key(): RuleKey
    {
        return RuleKey::CollectorFailing;
    }

    public function evaluate(RuleContext $context): ?RuleViolation
    {
        $limit = (int) ($context->thresholds($this->key())['consecutive_failures'] ?? 3);

        $failing = $context->checks->first(
            fn (Check $check): bool => $check->consecutive_failures >= $limit,
        );

        if (! $failing instanceof Check) {
            return null;
        }

        $assetName = $context->asset($failing->asset_id)?->name ?? 'un activo';

        return new RuleViolation(
            rule: $this->key(),
            severity: IncidentSeverity::Warning,
            title: sprintf('La recolección de "%s" está fallando', $failing->name),
            description: sprintf(
                'El check "%s" de %s ha fallado %d veces consecutivas. Último error: %s. Esto es un problema de OpsEvidence, no del activo.',
                $failing->name,
                $assetName,
                $failing->consecutive_failures,
                $failing->last_error ?? 'sin detalle',
            ),
            assetId: $failing->asset_id,
            recommendation: 'Revisar la configuración del check y la conectividad desde OpsEvidence hacia el activo.',
        );
    }
}
