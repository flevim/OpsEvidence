<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum IncidentSeverity: string
{
    use HasValues;

    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Informativo',
            self::Warning => 'Advertencia',
            self::Critical => 'Crítico',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Warning => 'warning',
            self::Critical => 'error',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Warning => 2,
            self::Critical => 3,
        };
    }
}
