<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Schema;

use Illuminate\Database\Query\Builder;

/** Shared storage-level schema conventions used across control and data planes. */
final class SchemaStorage
{
    public const DEFINITION_METADATA_CONTEXT = 'dp_record_definitions.metadata';

    public static function orderedFields(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('path');
    }
}
