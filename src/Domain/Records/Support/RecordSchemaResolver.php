<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Support;

use LogicException;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Core\Models\Record;

final class RecordSchemaResolver
{
    public static function fromRecord(Record $record): ?int
    {
        if (! $record->relationLoaded('recordDefinition')) {
            throw new LogicException('RecordSchemaResolver expects recordDefinition relation to be loaded.');
        }

        $definition = $record->getRelation('recordDefinition');
        if (! $definition instanceof RecordDefinition) {
            throw new LogicException(sprintf(
                'Record %d is missing an associated recordDefinition during schema resolution.',
                (int) $record->id,
            ));
        }

        $schemaId = $definition->schema_id;

        return is_int($schemaId) && $schemaId > 0 ? $schemaId : null;
    }
}
