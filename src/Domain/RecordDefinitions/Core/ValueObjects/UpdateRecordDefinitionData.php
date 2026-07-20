<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Core\ValueObjects;

final class UpdateRecordDefinitionData
{
    public function __construct(
        public readonly bool $hasName,
        public readonly ?string $name,
        public readonly bool $hasSchemaId,
        public readonly ?int $schemaId,
        public readonly bool $hasDisplayTemplate,
        public readonly ?string $displayTemplate,
    ) {}

    /**
     * @param array<string, mixed> $validated
     */
    public static function fromValidated(array $validated): self
    {
        $schemaId = null;
        if (array_key_exists('schema_id', $validated) && $validated['schema_id'] !== null) {
            $schemaId = is_numeric($validated['schema_id']) ? (int) $validated['schema_id'] : null;
        }

        return new self(
            hasName: array_key_exists('name', $validated),
            name: array_key_exists('name', $validated) ? (is_string($validated['name']) ? $validated['name'] : null) : null,
            hasSchemaId: array_key_exists('schema_id', $validated),
            schemaId: $schemaId,
            hasDisplayTemplate: array_key_exists('display_template', $validated),
            displayTemplate: array_key_exists('display_template', $validated) ? (is_string($validated['display_template']) ? $validated['display_template'] : null) : null,
        );
    }

    /**
     * @return array{name?: string|null, schema_id?: int|null, display_template?: string|null}
     */
    public function toArray(): array
    {
        $payload = [];

        if ($this->hasName) {
            $payload['name'] = $this->name;
        }

        if ($this->hasSchemaId) {
            $payload['schema_id'] = $this->schemaId;
        }

        if ($this->hasDisplayTemplate) {
            $payload['display_template'] = $this->displayTemplate;
        }

        return $payload;
    }
}
