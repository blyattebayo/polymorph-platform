<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Events;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;

final class EloquentRecordDefinitionSchemaCode implements RecordDefinitionSchemaCode
{
    public function forDefinition(int $recordDefinitionId): ?string
    {
        $code = RecordDefinition::find($recordDefinitionId)?->schema()?->value('code');

        return is_string($code) && $code !== '' ? $code : null;
    }
}
