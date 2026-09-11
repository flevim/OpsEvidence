<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum IncidentStatus: string
{
    use HasValues;

    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::Acknowledged => 'Reconocido',
            self::Resolved => 'Resuelto',
            self::Ignored => 'Ignorado',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Open || $this === self::Acknowledged;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'error',
            self::Acknowledged => 'warning',
            self::Resolved => 'success',
            self::Ignored => 'neutral',
        };
    }
}
