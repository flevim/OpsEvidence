<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

class WebsiteDownRule implements Rule
{
    public function key(): RuleKey
    {
        return RuleKey::WebsiteDown;
    }

    public function evaluate(RuleContext $context): ?RuleViolation
    {
        foreach ($context->evidenceOfType(CheckType::HttpStatus) as $evidence) {
            if ($evidence->status !== EvidenceStatus::Critical) {
                continue;
            }

            $assetName = $context->asset($evidence->asset_id)?->name ?? 'El sitio';

            return new RuleViolation(
                rule: $this->key(),
                severity: IncidentSeverity::Critical,
                title: "{$assetName} no está respondiendo",
                description: $evidence->data['error'] ?? 'La comprobación HTTP no obtuvo respuesta correcta.',
                assetId: $evidence->asset_id,
                evidenceId: $evidence->id,
                recommendation: 'Verificar que el servicio esté en ejecución y que el servidor web responda.',
            );
        }

        return null;
    }
}
