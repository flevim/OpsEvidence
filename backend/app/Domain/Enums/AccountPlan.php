<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum AccountPlan: string
{
    use HasValues;

    case Freelancer = 'freelancer';
    case Msp = 'msp';
    case MspPro = 'msp_pro';

    public function label(): string
    {
        return match ($this) {
            self::Freelancer => 'Freelancer',
            self::Msp => 'MSP',
            self::MspPro => 'MSP Pro',
        };
    }

    /**
     * Límite de clientes del plan. null = sin límite práctico.
     */
    public function clientLimit(): ?int
    {
        return match ($this) {
            self::Freelancer => 5,
            self::Msp => 25,
            self::MspPro => null,
        };
    }

    public function monthlyPriceUsd(): int
    {
        return match ($this) {
            self::Freelancer => 24,
            self::Msp => 64,
            self::MspPro => 124,
        };
    }
}
