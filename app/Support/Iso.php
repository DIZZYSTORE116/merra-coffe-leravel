<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Iso
{
    /** Format ISO-8601 UTC dengan milidetik, sama seperti JSON.stringify(Date) di JavaScript. */
    public static function format(?CarbonInterface $date): ?string
    {
        return $date?->copy()->setTimezone('UTC')->format('Y-m-d\TH:i:s.v\Z');
    }
}
