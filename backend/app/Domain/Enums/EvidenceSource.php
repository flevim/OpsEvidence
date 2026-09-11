<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum EvidenceSource: string
{
    use HasValues;

    case Check = 'check';
    case Agent = 'agent';
    case Webhook = 'webhook';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Check => 'Recolección automática',
            self::Agent => 'Agente',
            self::Webhook => 'Webhook',
            self::Manual => 'Registro manual',
        };
    }
}
