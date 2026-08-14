<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

enum ReferenceDeletionPolicy: string
{
    case Restrict = 'restrict';
    case Nullify = 'nullify';
    case PreserveTombstone = 'preserve_tombstone';
    case Cascade = 'cascade';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
