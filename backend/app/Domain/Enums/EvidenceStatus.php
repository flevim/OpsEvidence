<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum EvidenceStatus: string
{
    use HasValues;

    case Healthy = 'HEALTHY';
    case Warning = 'WARNING';
    case Critical = 'CRITICAL';
    case Unknown = 'UNKNOWN';
    case Failed = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Saludable',
            self::Warning => 'Atención',
            self::Critical => 'Crítico',
            self::Unknown => 'Desconocido',
            self::Failed => 'Fallo de recolección',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Warning => 'warning',
            self::Critical => 'error',
            self::Unknown => 'neutral',
            self::Failed => 'info',
        };
    }

    public function isProblem(): bool
    {
        return $this === self::Warning || $this === self::Critical || $this === self::Failed;
    }

    /**
     * Orden de gravedad para comparaciones y ordenamientos.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Healthy => 0,
            self::Unknown => 1,
            self::Warning => 2,
            self::Failed => 3,
            self::Critical => 4,
        };
    }

    /**
     * Severidad del incidente que corresponde a este estado, si corresponde abrir uno.
     */
    public function incidentSeverity(): ?IncidentSeverity
    {
        return match ($this) {
            self::Critical => IncidentSeverity::Critical,
            self::Warning, self::Failed => IncidentSeverity::Warning,
            default => null,
        };
    }
}
