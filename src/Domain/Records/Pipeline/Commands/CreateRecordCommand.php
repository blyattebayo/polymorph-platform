<?php

namespace Polymorph\Platform\Domain\Records\Pipeline\Commands;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;

final class CreateRecordCommand
{
    public function __construct(
        public readonly RecordDefinition $recordDefinition,
        public readonly array $dataJson,
        public readonly ?int $actorId = null,
        public readonly ?string $operationId = null,
    ) {}
}
