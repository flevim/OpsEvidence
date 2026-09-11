<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum EnvironmentType: string
{
    use HasValues;

    case Production = 'production';
    case Staging = 'staging';
    case Development = 'development';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Production => 'Producción',
            self::Staging => 'Staging',
            self::Development => 'Desarrollo',
            self::Other => 'Otro',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Production => 'error',
            self::Staging => 'warning',
            self::Development => 'info',
            self::Other => 'neutral',
        };
    }
}
