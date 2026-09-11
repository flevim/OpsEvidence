<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

class BackupFailedRule implements Rule
{
    public function key(): RuleKey
    {
        return RuleKey::BackupFailed;
    }

    public function evaluate(RuleContext $context): ?RuleViolation
    {
        foreach ($context->evidenceOfType(CheckType::BackupStatus) as $evidence) {
            if ($evidence->status !== EvidenceStatus::Critical) {
                continue;
            }

            $assetName = $context->asset($evidence->asset_id)?->name ?? 'el origen de backup';

            return new RuleViolation(
                rule: $this->key(),
                severity: IncidentSeverity::Critical,
                title: "Falló el backup de {$assetName}",
                description: $evidence->title,
                assetId: $evidence->asset_id,
                evidenceId: $evidence->id,
                recommendation: 'Revisar la salida del script de backup y reintentar la ejecución.',
            );
        }

        return null;
    }
}
