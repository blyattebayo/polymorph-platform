<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Commands;

use Polymorph\Platform\Domain\RecordDefinitions\Core\ValueObjects\CreateRecordDefinitionData;

final class CreateRecordDefinitionCommand
{
    public readonly CreateRecordDefinitionData $payload;

    /**
     * @param array{name: string, schema_id?: int|null, display_template?: string|null}|CreateRecordDefinitionData $payload
     */
    public function __construct(
        array|CreateRecordDefinitionData $payload,
    ) {
        if ($payload instanceof CreateRecordDefinitionData) {
            $this->payload = $payload;

            return;
        }

        $this->payload = new CreateRecordDefinitionData(
            name: (string) ($payload['name'] ?? ''),
            schemaId: array_key_exists('schema_id', $payload) && is_int($payload['schema_id']) ? $payload['schema_id'] : null,
            displayTemplate: array_key_exists('display_template', $payload) && is_string($payload['display_template']) ? $payload['display_template'] : null,
        );
    }
}
