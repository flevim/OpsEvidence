<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;

class Timezone
{
    public const DEFAULT = 'UTC';

    public static function utc(): DateTimeZone
    {
        return new DateTimeZone(self::DEFAULT);
    }

    /**
     * Convierte una fecha de un periodo de informe a limites UTC del dia.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function dayBounds(string $date, ?string $timezone = null): array
    {
        $zone = $timezone ?? self::DEFAULT;

        $start = CarbonImmutable::parse($date, $zone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($date, $zone)->endOfDay()->utc();

        return [$start, $end];
    }
}
