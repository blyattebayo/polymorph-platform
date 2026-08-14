<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Projection\FieldProjectionChanges;
use Polymorph\Platform\Domain\DataPlatform\Query\CompiledPredicate;
use Polymorph\Platform\Domain\DataPlatform\Query\QueryPredicate;
use Polymorph\Platform\Domain\DataPlatform\Serialization\CanonicalJson;
use Polymorph\Sdk\Data\FieldTypes\FieldTypeExtension;

final class SdkFieldTypeHandlerAdapter implements FieldTypeHandler
{
    private const QUERY_OPERATORS = [
        'eq', 'in', 'lt', 'lte', 'gt', 'gte', 'between',
        'contains', 'starts_with', 'matches', 'is_null', 'is_not_null',
    ];

    public function __construct(
        private readonly FieldTypeExtension $extension,
        private readonly CanonicalJson $canonicalJson,
    ) {}

    public function type(): string
    {
        return $this->extension->type();
    }

    public function validateSchema(FieldDefinition $field): void
    {
        $this->extension->validateSchema($this->field($field));
    }

    public function normalize(mixed $value, FieldDefinition $field, string $occurrence): mixed
    {
        return $this->extension->normalize($value, $this->field($field), $occurrence);
    }

    public function validateValue(mixed $value, FieldDefinition $field, string $occurrence): void
    {
        $this->extension->validateValue($value, $this->field($field), $occurrence);
    }

    public function collectBatchDependencies(mixed $value, FieldDefinition $field, string $occurrence, DependencySet $dependencies): void
    {
        $collected = $this->extension->collectBatchDependencies($value, $this->field($field), $occurrence);
        foreach (($collected['record_ids'] ?? []) as $id) {
            $dependencies->addRecord((int) $id);
        }
        foreach (($collected['media_ids'] ?? []) as $id) {
            $dependencies->addMedia((string) $id);
        }
    }

    public function validateResolvedDependencies(mixed $value, FieldDefinition $field, string $occurrence, ResolvedDependencies $dependencies): void
    {
        $this->extension->validateResolvedDependencies($value, $this->field($field), $occurrence, [
            'records' => $dependencies->records,
            'media' => $dependencies->media,
        ]);
    }

    public function buildProjectionChanges(mixed $value, FieldDefinition $field, string $occurrence): FieldProjectionChanges
    {
        $changes = $this->extension->buildProjectionChanges($value, $this->field($field), $occurrence);
        $refEdges = $this->validateRows(
            array_values($changes['ref_edges'] ?? []),
            ['field_id', 'occurrence', 'position', 'target_record_id', 'deletion_policy', 'projection_version'],
            function (array $edge) use ($field, $occurrence): void {
                $this->assertCommonProjectionRow($edge, $field, $occurrence);
                if (! is_int($edge['target_record_id'] ?? null) || $edge['target_record_id'] <= 0
                    || ! in_array($edge['deletion_policy'] ?? null, ReferenceDeletionPolicy::values(), true)) {
                    throw $this->extensionViolation('invalid_ref_projection', 'A plugin emitted an invalid ref projection row.');
                }
            },
        );
        $mediaEdges = $this->validateRows(
            array_values($changes['media_edges'] ?? []),
            ['field_id', 'occurrence', 'position', 'media_id', 'attachment', 'projection_version'],
            function (array $edge) use ($field, $occurrence): void {
                $this->assertCommonProjectionRow($edge, $field, $occurrence);
                if (! is_string($edge['media_id'] ?? null) || trim($edge['media_id']) === ''
                    || ! is_array($edge['attachment'] ?? null)) {
                    throw $this->extensionViolation('invalid_media_projection', 'A plugin emitted an invalid media projection row.');
                }
            },
        );
        $uniqueValues = $this->validateRows(
            array_values($changes['unique_values'] ?? []),
            ['field_id', 'value_hash', 'value', 'projection_version'],
            function (array $row) use ($field): void {
                if (($row['field_id'] ?? null) !== $field->id
                    || ($row['projection_version'] ?? null) !== $field->projectionVersion
                    || ! is_string($row['value_hash'] ?? null)
                    || ! hash_equals($row['value_hash'], $this->canonicalJson->hash($row['value'] ?? null))) {
                    throw $this->extensionViolation('invalid_unique_projection', 'A plugin emitted an invalid unique projection row.');
                }
            },
        );
        $searchValues = array_values($changes['search_values'] ?? []);
        if (array_filter($searchValues, static fn (mixed $item): bool => ! is_string($item)) !== []) {
            throw $this->extensionViolation('invalid_search_projection', 'A plugin emitted a non-string search projection value.');
        }
        $displayValue = $changes['display_value'] ?? null;
        if ($displayValue !== null && ! is_string($displayValue)) {
            throw $this->extensionViolation('invalid_display_projection', 'A plugin emitted a non-string display projection value.');
        }

        return new FieldProjectionChanges(
            refEdges: $refEdges,
            mediaEdges: $mediaEdges,
            uniqueValues: $uniqueValues,
            searchValues: $searchValues,
            displayValue: $displayValue,
        );
    }

    public function supportedQueryOperators(): array
    {
        $operators = $this->extension->supportedQueryOperators();
        if (! array_is_list($operators)
            || array_filter($operators, static fn (mixed $operator): bool => ! is_string($operator)
                || ! in_array($operator, self::QUERY_OPERATORS, true)) !== []
            || count(array_unique($operators)) !== count($operators)) {
            throw $this->extensionViolation(
                'invalid_query_operators',
                'A plugin declared an invalid query operator capability list.',
            );
        }

        return $operators;
    }

    public function compileQuery(QueryPredicate $predicate): CompiledPredicate
    {
        if (! in_array($predicate->operator, $this->supportedQueryOperators(), true)) {
            throw DataPlatformBadRequest::because(
                'unsupported_field_operator',
                "Operator '{$predicate->operator}' is not supported by '{$this->type()}'.",
                ['field_type' => $this->type(), 'operator' => $predicate->operator],
            );
        }
        $compiled = $this->extension->compileQuery([
            'field' => $this->field($predicate->field),
            'operator' => $predicate->operator,
            'value' => $predicate->value,
        ]);
        $strategy = $compiled['strategy'] ?? null;
        $operator = $compiled['operator'] ?? null;
        $cast = $compiled['cast'] ?? null;
        if (! in_array($strategy, ['jsonb', 'ref_edge', 'media_edge'], true)
            || $operator !== $predicate->operator
            || ($cast !== null && ! in_array($cast, [
                'text', 'integer', 'bigint', 'numeric', 'double precision', 'boolean', 'timestamp', 'timestamptz',
            ], true))) {
            throw $this->extensionViolation('unsafe_compiled_query', 'A plugin emitted an unsafe compiled query declaration.');
        }

        return new CompiledPredicate(
            strategy: $strategy,
            cast: $cast,
        );
    }

    /** @internal Stable identity used to make late tag synchronization idempotent. */
    public function sourceId(): int
    {
        return spl_object_id($this->extension);
    }

    /** @return array<string,mixed> */
    private function field(FieldDefinition $field): array
    {
        return [
            'id' => $field->id,
            'path' => $field->path,
            'name' => $field->name,
            'type' => $field->typeName(),
            'cardinality' => $field->cardinality->value,
            'system' => $field->system,
            'projection_version' => $field->projectionVersion,
            'constraints' => $field->constraints,
            'metadata' => $field->metadata,
            'parent_id' => $field->parentId,
            'multi_valued' => $field->multiValued,
            'position' => $field->position,
        ];
    }

    /**
     * @param  list<mixed>  $rows
     * @param  list<string>  $allowedKeys
     * @param  callable(array<string,mixed>):void  $validate
     * @return list<array<string,mixed>>
     */
    private function validateRows(array $rows, array $allowedKeys, callable $validate): array
    {
        foreach ($rows as $row) {
            if (! is_array($row) || array_diff(array_keys($row), $allowedKeys) !== []) {
                throw $this->extensionViolation('invalid_projection_shape', 'A plugin emitted an invalid projection row shape.');
            }
            $validate($row);
        }

        return $rows;
    }

    /** @param array<string,mixed> $row */
    private function assertCommonProjectionRow(array $row, FieldDefinition $field, string $occurrence): void
    {
        $rowOccurrence = $row['occurrence'] ?? null;
        if (($row['field_id'] ?? null) !== $field->id
            || ! is_string($rowOccurrence)
            || ! OccurrencePath::isSameOrNestedItem($rowOccurrence, $occurrence)
            || ! is_int($row['position'] ?? null)
            || $row['position'] < 0
            || ($row['projection_version'] ?? null) !== $field->projectionVersion) {
            throw $this->extensionViolation('projection_outside_field_contract', 'A plugin emitted projection data outside its field contract.');
        }
    }

    private function extensionViolation(string $reason, string $message): DataPlatformInvariantViolation
    {
        return DataPlatformInvariantViolation::because(
            'field_type_extension_'.$reason,
            $message,
            ['field_type' => $this->extension->type()],
        );
    }
}
