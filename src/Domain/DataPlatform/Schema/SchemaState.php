<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Schema;

enum SchemaState: string
{
    case Draft = 'draft';
    case Validating = 'validating';
    case Backfilling = 'backfilling';
    case Published = 'published';
    case Archived = 'archived';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => $next === self::Validating,
            self::Validating => in_array($next, [self::Draft, self::Backfilling, self::Published], true),
            self::Backfilling => $next === self::Published,
            self::Published => $next === self::Archived,
            self::Archived => false,
        };
    }
}
