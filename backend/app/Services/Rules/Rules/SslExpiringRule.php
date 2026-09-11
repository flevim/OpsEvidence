<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

class SslExpiringRule implements Rule
{
    public function key(): RuleKey
    {
        return RuleKey::SslExpiring;
    }

    public function evaluate(RuleContext $context): ?RuleViolation
    {
        $worst = null;

        foreach ($context->evidenceOfType(CheckType::SslExpiration) as $evidence) {
            if (! in_array($evidence->status, [EvidenceStatus::Warning, EvidenceStatus::Critical], true)) {
                continue;
            }

            if ($worst === null || $evidence->status->weight() > $worst->status->weight()) {
                $worst = $evidence;
            }
        }

        if ($worst === null) {
            return null;
        }

        $days = (int) ($worst->value_numeric ?? 0);
        $host = $worst->data['host'] ?? $context->asset($worst->asset_id)?->name ?? 'el sitio';

        return new RuleViolation(
            rule: $this->key(),
            severity: $worst->status === EvidenceStatus::Critical
                ? IncidentSeverity::Critical
                : IncidentSeverity::Warning,
            title: $days < 0
                ? "El certificado SSL de {$host} está vencido"
                : "El certificado SSL de {$host} vence en {$days} días",
            description: $worst->title,
            assetId: $worst->asset_id,
            evidenceId: $worst->id,
            recommendation: 'Renovar el certificado y verificar la renovación automática.',
        );
    }
}
