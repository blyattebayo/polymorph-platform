<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Core\ValueObjects;

final class CreateRecordDefinitionData
{
    public function __construct(
        public readonly string $name,
        public readonly ?int $schemaId = null,
        public readonly ?string $displayTemplate = null,
    ) {}

    /**
     * @return array{
     *   name: string,
     *   schema_id: int|null,
     *   display_template: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'schema_id' => $this->schemaId,
            'display_template' => $this->displayTemplate,
        ];
    }
}
