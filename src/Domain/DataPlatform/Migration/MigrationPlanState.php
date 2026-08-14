<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

enum MigrationPlanState: string
{
    case Draft = 'draft';
    case Running = 'running';
    case RunningWithErrors = 'running_with_errors';
    case Completed = 'completed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $state): string => $state->value, self::cases());
    }
}
