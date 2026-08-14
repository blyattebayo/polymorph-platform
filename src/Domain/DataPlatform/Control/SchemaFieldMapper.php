<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Fields\Cardinality;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Maps one dp_schema_fields row into the stable domain representation. */
final class SchemaFieldMapper
{
    public function __construct(private readonly DatabaseJson $json) {}

    public function fromRow(object|array $row, bool $multiValued = false): FieldDefinition
    {
        $row = (array) $row;
        $cardinalityValue = (string) $row['cardinality'];
        $cardinality = Cardinality::tryFrom($cardinalityValue);
        if (! $cardinality instanceof Cardinality) {
            throw DataPlatformInvariantViolation::because(
                'unknown_stored_field_cardinality',
                'A stored schema field has an unknown cardinality.',
                ['field_id' => (string) $row['field_id'], 'cardinality' => $cardinalityValue],
            );
        }

        return new FieldDefinition(
            id: (string) $row['field_id'],
            path: (string) $row['path'],
            name: (string) $row['name'],
            type: (string) $row['type'],
            cardinality: $cardinality,
            system: (bool) $row['is_system'],
            projectionVersion: (int) $row['projection_version'],
            constraints: $this->json->decodeMap($row['constraints'] ?? null, 'dp_schema_fields.constraints'),
            metadata: $this->json->decodeMap($row['metadata'] ?? null, 'dp_schema_fields.metadata'),
            parentId: isset($row['parent_field_id']) && $row['parent_field_id'] !== null
                ? (string) $row['parent_field_id']
                : null,
            multiValued: $multiValued,
            position: (int) $row['position'],
        );
    }

    /** @return list<FieldDefinition> */
    public function fromRows(iterable $rows): array
    {
        $rows = array_map(static fn (object|array $row): array => (array) $row, [...$rows]);
        $cardinalityByPath = [];
        foreach ($rows as $row) {
            $cardinalityByPath[(string) $row['path']] = (string) $row['cardinality'];
        }

        return array_map(function (array $row) use ($cardinalityByPath): FieldDefinition {
            $prefix = [];
            $multiValued = false;
            foreach (explode('.', (string) $row['path']) as $part) {
                $prefix[] = $part;
                if (($cardinalityByPath[implode('.', $prefix)] ?? null) === Cardinality::MANY->value) {
                    $multiValued = true;
                    break;
                }
            }

            return $this->fromRow($row, $multiValued);
        }, $rows);
    }
}
