<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

class GithubWorkflowFailedRule implements Rule
{
    public function key(): RuleKey
    {
        return RuleKey::GithubWorkflowFailed;
    }

    public function evaluate(RuleContext $context): ?RuleViolation
    {
        foreach ($context->evidenceOfType(CheckType::GithubWorkflow) as $evidence) {
            if ($evidence->status !== EvidenceStatus::Critical) {
                continue;
            }

            $repo = trim(($evidence->data['owner'] ?? '').'/'.($evidence->data['repo'] ?? ''), '/');
            $branch = $evidence->data['branch'] ?? 'rama desconocida';
            $sha = $evidence->data['short_sha'] ?? '';

            return new RuleViolation(
                rule: $this->key(),
                severity: IncidentSeverity::Critical,
                title: "El último despliegue de {$repo} falló",
                description: sprintf('El workflow en la rama %s (%s) terminó con error.', $branch, $sha),
                assetId: $evidence->asset_id,
                evidenceId: $evidence->id,
                recommendation: 'Revisar los logs del workflow en GitHub y corregir la causa del fallo.',
            );
        }

        return null;
    }
}
