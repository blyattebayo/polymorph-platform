<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeRegistry;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCompiler;
use Polymorph\Platform\Domain\DataPlatform\Serialization\CanonicalJson;

/** Validates complete schema snapshots and produces their canonical checksum. */
final class SchemaValidator
{
    public function __construct(
        private readonly FieldTypeRegistry $types,
        private readonly CanonicalJson $canonicalJson,
        private readonly SchemaCompiler $compiler,
    ) {}

    /** @param list<FieldDefinition> $fields */
    public function validate(array $fields): string
    {
        if ($fields === []) {
            throw DataPlatformBadRequest::because('schema_has_no_fields', 'A schema cannot be validated without fields.');
        }

        $fields = $this->compiler->compile($fields)->fields();
        foreach ($fields as $field) {
            $this->types->get($field->type)->validateSchema($field);
        }

        $payloads = array_map($this->checksumPayload(...), $fields);
        usort($payloads, static fn (array $left, array $right): int => [
            $left['position'], $left['path'], $left['id'],
        ] <=> [
            $right['position'], $right['path'], $right['id'],
        ]);

        return $this->canonicalJson->hash($payloads);
    }

    /** @param list<FieldDefinition> $fields */
    public function assertUniqueIdentityAndTree(array $fields): void
    {
        $this->compiler->compile($fields);
    }

    /** @return array<string, mixed> */
    private function checksumPayload(FieldDefinition $field): array
    {
        return [
            'id' => $field->id,
            'path' => $field->path,
            'type' => $field->typeName(),
            'cardinality' => $field->cardinality->value,
            'system' => $field->system,
            'projection_version' => $field->projectionVersion,
            'constraints' => $field->constraints,
            'metadata' => $field->metadata,
            'parent_id' => $field->parentId,
            'position' => $field->position,
        ];
    }
}
