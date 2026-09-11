<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum TokenAbility: string
{
    use HasValues;

    case EvidenceWrite = 'evidence:write';
    case ActivityWrite = 'activity:write';

    public function label(): string
    {
        return match ($this) {
            self::EvidenceWrite => 'Enviar evidencia',
            self::ActivityWrite => 'Registrar actividad',
        };
    }
}
