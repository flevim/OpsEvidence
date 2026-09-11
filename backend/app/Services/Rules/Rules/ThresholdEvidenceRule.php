<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\IncidentSeverity;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

/**
 * Regla base para umbrales numericos sobre la ultima evidencia de un tipo.
 *
 * Cubre DISK_USAGE, MEMORY_USAGE y CPU_USAGE, que solo se diferencian en el
 * tipo de evidencia, las claves de umbral y el texto que muestran.
 */
abstract class ThresholdEvidenceRule implements Rule
{
    abstract protected function checkType(): CheckType;

    abstract protected function warningKey(): string;

    abstract protected function criticalKey(): string;

    abstract protected function unit(): string;

    abstract protected function subject(): string;

    abstract protected function recommendation(float $percent): string;

    public function evaluate(RuleContext $context): ?RuleViolation
    {
        $thresholds = $context->thresholds($this->key());
        $warning = (float) ($thresholds[$this->warningKey()] ?? 80);
        $critical = (float) ($thresholds[$this->criticalKey()] ?? 90);

        $worst = null;

        foreach ($context->evidenceOfType($this->checkType()) as $evidence) {
            if ($evidence->value_numeric === null) {
                continue;
            }

            if ($worst === null || $evidence->value_numeric > $worst->value_numeric) {
                $worst = $evidence;
            }
        }

        if ($worst === null) {
            return null;
        }

        $severity = match (true) {
            $worst->value_numeric >= $critical => IncidentSeverity::Critical,
            $worst->value_numeric >= $warning => IncidentSeverity::Warning,
            default => null,
        };

        if ($severity === null) {
            return null;
        }

        $target = $worst->data['mountpoint'] ?? $worst->data['device'] ?? null;
        $label = $this->subject().($target !== null ? " ({$target})" : '');

        return new RuleViolation(
            rule: $this->key(),
            severity: $severity,
            title: sprintf('%s al %s%s', $label, number_format($worst->value_numeric, 1, ',', '.'), $this->unit()),
            description: $worst->title,
            assetId: $worst->asset_id,
            evidenceId: $worst->id,
            recommendation: $this->recommendation($worst->value_numeric),
        );
    }

    protected function exceedsCritical(float $value, float $critical): bool
    {
        return $value >= $critical;
    }

    protected function isProblemStatus(?EvidenceStatus $status): bool
    {
        return $status !== null && $status->isProblem();
    }
}
