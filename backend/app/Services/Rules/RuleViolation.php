<?php

namespace App\Services\Rules;

use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;

/**
 * Incumplimiento detectado por una regla.
 *
 * Es un objeto de valor puro: no sabe nada de incidentes ni de base de datos.
 * Quien lo convierte en incidente es el IncidentManager.
 */
final readonly class RuleViolation
{
    public function __construct(
        public RuleKey $rule,
        public IncidentSeverity $severity,
        public string $title,
        public string $description,
        public ?int $assetId = null,
        public ?int $evidenceId = null,
        public ?string $recommendation = null,
    ) {}

    public function signature(): string
    {
        return $this->rule->value.':'.($this->assetId ?? 'global');
    }
}
