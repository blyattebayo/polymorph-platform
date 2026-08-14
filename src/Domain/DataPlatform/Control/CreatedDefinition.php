<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

final readonly class CreatedDefinition
{
    public function __construct(
        public int $definitionId,
        public string $schemaVersionId,
    ) {}

    /** @return array{definition_id:int,schema_version_id:string} */
    public function toArray(): array
    {
        return [
            'definition_id' => $this->definitionId,
            'schema_version_id' => $this->schemaVersionId,
        ];
    }
}
