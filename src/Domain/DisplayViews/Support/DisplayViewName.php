<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DisplayViews\Support;

use InvalidArgumentException;

final class DisplayViewName
{
    public static function forRecordDefinition(int $recordDefinitionId): string
    {
        if ($recordDefinitionId <= 0) {
            throw new InvalidArgumentException('Record definition id must be positive for view naming');
        }

        return 'display_rd_'.$recordDefinitionId.'_v';
    }

    public static function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
