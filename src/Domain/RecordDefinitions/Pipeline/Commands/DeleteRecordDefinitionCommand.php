<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Commands;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;

final class DeleteRecordDefinitionCommand
{
    public function __construct(
        public readonly RecordDefinition $recordDefinition,
        public readonly bool $force = false,
    ) {}
}
