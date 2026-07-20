<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Events;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;

final class RecordDefinitionSchemaChanged
{
    public function __construct(
        public readonly RecordDefinition $recordDefinition,
    ) {}
}
