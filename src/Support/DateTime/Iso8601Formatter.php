<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\DateTime;

use Carbon\CarbonImmutable;

final class Iso8601Formatter
{
    public static function format(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
