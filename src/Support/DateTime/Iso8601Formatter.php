<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\DateTime;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class Iso8601Formatter
{
    public static function format(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toIso8601String();
        }

        return CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
