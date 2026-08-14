<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

enum SchemaState: string
{
    case Draft = 'draft';
    case Validating = 'validating';
    case Backfilling = 'backfilling';
    case Published = 'published';
    case Archived = 'archived';

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
