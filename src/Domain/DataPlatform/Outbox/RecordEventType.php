<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Outbox;

enum RecordEventType: string
{
    case Created = 'data.record.created';
    case Updated = 'data.record.updated';
    case Migrated = 'data.record.migrated';
    case Deleted = 'data.record.deleted';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
