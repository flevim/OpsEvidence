<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum ReportStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Generating = 'generating';
    case Ready = 'ready';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Generating => 'Generando',
            self::Ready => 'Listo',
            self::Sent => 'Enviado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Generating => 'info',
            self::Ready => 'warning',
            self::Sent => 'success',
        };
    }
}
