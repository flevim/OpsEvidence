<?php

namespace App\Services\Evidence;

use App\Domain\Enums\EvidenceStatus;

/**
 * Capa de normalizacion: traduce el dato crudo de cada fuente al conjunto
 * canonico de estados de OpsEvidence.
 *
 * Funciones puras y probables: no consultan base de datos ni red.
 * La tabla de mapeo esta documentada en docs/evidence-model.md.
 */
class EvidenceNormalizer
{
    public function fromHttpStatusCode(?int $statusCode): EvidenceStatus
    {
        if ($statusCode === null) {
            return EvidenceStatus::Failed;
        }

        return match (true) {
            $statusCode >= 200 && $statusCode < 400 => EvidenceStatus::Healthy,
            $statusCode >= 400 && $statusCode < 500 => EvidenceStatus::Warning,
            $statusCode >= 500 => EvidenceStatus::Critical,
            default => EvidenceStatus::Unknown,
        };
    }

    public function fromSslDaysRemaining(?int $days, int $warningDays = 30, int $criticalDays = 7): EvidenceStatus
    {
        if ($days === null) {
            return EvidenceStatus::Failed;
        }

        return match (true) {
            $days < $criticalDays => EvidenceStatus::Critical,
            $days < $warningDays => EvidenceStatus::Warning,
            default => EvidenceStatus::Healthy,
        };
    }

    public function fromPercent(?float $value, float $warning, float $critical): EvidenceStatus
    {
        if ($value === null) {
            return EvidenceStatus::Unknown;
        }

        return match (true) {
            $value >= $critical => EvidenceStatus::Critical,
            $value >= $warning => EvidenceStatus::Warning,
            default => EvidenceStatus::Healthy,
        };
    }

    public function fromBackupStatus(?string $raw): EvidenceStatus
    {
        if ($raw === null || $raw === '') {
            return EvidenceStatus::Unknown;
        }

        return match (mb_strtolower(trim($raw))) {
            'success', 'ok', 'completed', 'done', 'passed' => EvidenceStatus::Healthy,
            'warning', 'partial', 'success_with_warnings' => EvidenceStatus::Warning,
            'failed', 'failure', 'error', 'aborted' => EvidenceStatus::Critical,
            'running', 'in_progress', 'pending' => EvidenceStatus::Unknown,
            default => EvidenceStatus::Unknown,
        };
    }

    public function fromDockerState(bool $running, ?string $health = null, bool $hasRestartPolicy = false): EvidenceStatus
    {
        if (! $running) {
            return $hasRestartPolicy ? EvidenceStatus::Warning : EvidenceStatus::Critical;
        }

        return match (mb_strtolower((string) $health)) {
            'unhealthy' => EvidenceStatus::Critical,
            'starting' => EvidenceStatus::Warning,
            'none', '' => EvidenceStatus::Healthy,
            default => EvidenceStatus::Healthy,
        };
    }

    public function fromGithubConclusion(?string $conclusion, ?string $runStatus): EvidenceStatus
    {
        if ($runStatus !== null && ! in_array($runStatus, ['completed'], true)) {
            return EvidenceStatus::Unknown;
        }

        return match ($conclusion) {
            'success' => EvidenceStatus::Healthy,
            'failure', 'timed_out', 'startup_failure' => EvidenceStatus::Critical,
            'cancelled', 'skipped', 'neutral', 'action_required' => EvidenceStatus::Warning,
            null => EvidenceStatus::Unknown,
            default => EvidenceStatus::Unknown,
        };
    }

    public function fromSecurityUpdates(int $count, int $criticalCount = 10): EvidenceStatus
    {
        return match (true) {
            $count >= $criticalCount => EvidenceStatus::Critical,
            $count > 0 => EvidenceStatus::Warning,
            default => EvidenceStatus::Healthy,
        };
    }

    public function fromUptimeSeconds(?int $seconds): EvidenceStatus
    {
        if ($seconds === null) {
            return EvidenceStatus::Unknown;
        }

        return $seconds > 0 ? EvidenceStatus::Healthy : EvidenceStatus::Critical;
    }

    /**
     * Estado cuando la recoleccion en si falla: nunca CRITICAL, porque el
     * activo podria estar bien y el problema ser nuestro
     * (ver docs/evidence-model.md, seccion 3).
     */
    public function fromCollectionFailure(): EvidenceStatus
    {
        return EvidenceStatus::Failed;
    }
}
