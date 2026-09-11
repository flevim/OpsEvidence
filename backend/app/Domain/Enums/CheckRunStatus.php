<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum CheckRunStatus: string
{
    use HasValues;

    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Partial = 'partial';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'En ejecución',
            self::Success => 'Correcta',
            self::Failed => 'Fallida',
            self::Partial => 'Parcial',
            self::Skipped => 'Omitida',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Success || $this === self::Partial;
    }
}
