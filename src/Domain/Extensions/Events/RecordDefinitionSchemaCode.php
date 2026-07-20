<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Events;

/**
 * Resolves the schema storage code for a record definition id. Injected into the SDK event
 * bridge so its parse/dispatch logic is unit-testable without the database.
 */
interface RecordDefinitionSchemaCode
{
    public function forDefinition(int $recordDefinitionId): ?string;
}
