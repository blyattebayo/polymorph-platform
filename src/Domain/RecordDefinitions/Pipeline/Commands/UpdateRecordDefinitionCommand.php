<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Commands;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\RecordDefinitions\Core\ValueObjects\UpdateRecordDefinitionData;

final class UpdateRecordDefinitionCommand
{
    public readonly UpdateRecordDefinitionData $payload;

    /**
     * @param  array{name?: string, schema_id?: int|null, display_template?: string|null}|UpdateRecordDefinitionData  $payload
     */
    public function __construct(
        public readonly RecordDefinition $recordDefinition,
        array|UpdateRecordDefinitionData $payload,
    ) {
        $this->payload = $payload instanceof UpdateRecordDefinitionData
            ? $payload
            : UpdateRecordDefinitionData::fromValidated($payload);
    }
}
