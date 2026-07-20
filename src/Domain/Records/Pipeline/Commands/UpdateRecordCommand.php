<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Commands;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;

/**
 * Command to update an existing record
 */
final class UpdateRecordCommand
{
    public function __construct(
        public readonly int $recordId,
        public readonly RecordDefinition $recordDefinition,
        public readonly array $dataJson,
        public readonly ?int $actorId = null,
        public readonly ?string $operationId = null,
    ) {}
}
