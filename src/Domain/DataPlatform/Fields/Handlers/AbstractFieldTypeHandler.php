<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Fields\Cardinality;
use Polymorph\Platform\Domain\DataPlatform\Fields\DependencySet;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeHandler;
use Polymorph\Platform\Domain\DataPlatform\Fields\OccurrencePath;
use Polymorph\Platform\Domain\DataPlatform\Fields\ResolvedDependencies;
use Polymorph\Platform\Domain\DataPlatform\Projection\FieldProjectionChanges;
use Polymorph\Platform\Domain\DataPlatform\Query\CompiledPredicate;
use Polymorph\Platform\Domain\DataPlatform\Query\QueryPredicate;
use Polymorph\Platform\Domain\DataPlatform\Serialization\CanonicalJson;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

abstract class AbstractFieldTypeHandler implements FieldTypeHandler
{
    private const COMMON_OPERATORS = ['eq', 'in', 'is_null', 'is_not_null'];

    public function __construct(private readonly CanonicalJson $canonicalJson) {}

    public function validateSchema(FieldDefinition $field): void
    {
        if ($field->projectionVersion < 1) {
            throw DataValidationException::one(
                'invalid_projection_version',
                'Projection version must be positive.',
                $field->path,
            );
        }
        foreach (['required', 'allow_duplicates'] as $constraint) {
            if (array_key_exists($constraint, $field->constraints) && ! is_bool($field->constraints[$constraint])) {
                throw DataValidationException::one(
                    'invalid_schema_constraint',
                    "Constraint '{$constraint}' must be boolean.",
                    $field->path,
                );
            }
        }
        $this->assertNonNegativeIntegerRange($field, 'min_items', 'max_items');
        foreach (['unique', 'search', 'display', 'indexed'] as $metadata) {
            if (array_key_exists($metadata, $field->metadata) && ! is_bool($field->metadata[$metadata])) {
                throw DataValidationException::one('invalid_schema_metadata', "Metadata '{$metadata}' must be boolean.", $field->path);
            }
        }
        if ($field->cardinality === Cardinality::MANY
            && ($field->metadata['unique'] ?? false) === true
            && ($field->constraints['allow_duplicates'] ?? null) !== false) {
            throw DataValidationException::one(
                'unique_requires_no_duplicates',
                'A unique many-valued field must set allow_duplicates to false.',
                $field->path,
            );
        }
    }

    public function normalize(mixed $value, FieldDefinition $field, string $occurrence): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($field->cardinality === Cardinality::MANY) {
            if (! is_array($value) || ! array_is_list($value)) {
                throw DataValidationException::one('cardinality', 'Expected a list.', $field->path, $occurrence);
            }

            return array_map(
                fn (mixed $item, int $index): mixed => $this->normalizeOne(
                    $item,
                    $field,
                    $this->itemOccurrence($field, $occurrence, $index),
                ),
                $value,
                array_keys($value),
            );
        }

        return $this->normalizeOne($value, $field, $occurrence);
    }

    public function validateValue(mixed $value, FieldDefinition $field, string $occurrence): void
    {
        if ($value === null) {
            if (($field->constraints['required'] ?? false) === true) {
                throw DataValidationException::one('required', 'Value is required.', $field->path, $occurrence);
            }

            return;
        }

        $items = $field->cardinality === Cardinality::MANY ? $value : [$value];
        if (! is_array($items)) {
            throw DataValidationException::one('cardinality', 'Expected a list.', $field->path, $occurrence);
        }

        $count = count($items);
        $min = $field->constraints['min_items'] ?? null;
        $max = $field->constraints['max_items'] ?? null;
        if (is_int($min) && $count < $min) {
            throw DataValidationException::one('min_items', "At least {$min} item(s) are required.", $field->path, $occurrence);
        }
        if (is_int($max) && $count > $max) {
            throw DataValidationException::one('max_items', "At most {$max} item(s) are allowed.", $field->path, $occurrence);
        }
        $duplicatesForbidden = ($field->constraints['allow_duplicates'] ?? true) === false
            || ($field->metadata['unique'] ?? false) === true;
        if ($duplicatesForbidden && count(array_unique(array_map(
            fn (mixed $item): string => $this->canonicalJson->hash($item),
            $items,
        ))) !== $count) {
            throw DataValidationException::one('duplicates', 'Duplicate values are not allowed.', $field->path, $occurrence);
        }

        foreach ($items as $index => $item) {
            $this->validateOne($item, $field, $this->itemOccurrence($field, $occurrence, $index));
        }
    }

    public function collectBatchDependencies(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
        DependencySet $dependencies,
    ): void {}

    public function validateResolvedDependencies(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
        ResolvedDependencies $dependencies,
    ): void {}

    public function buildProjectionChanges(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
    ): FieldProjectionChanges {
        if ($value === null) {
            return FieldProjectionChanges::none();
        }

        $values = $this->values($value, $field);
        $unique = [];
        if (($field->metadata['unique'] ?? false) === true) {
            foreach ($values as $item) {
                $unique[] = [
                    'field_id' => $field->id,
                    'value_hash' => $this->canonicalJson->hash($item),
                    'value' => $item,
                    'projection_version' => $field->projectionVersion,
                ];
            }
        }

        $search = ($field->metadata['search'] ?? false) === true
            ? array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : $this->canonicalJson->encode($item), $values)
            : [];

        $display = ($field->metadata['display'] ?? false) === true
            ? (is_scalar($values[0] ?? null) ? (string) $values[0] : null)
            : null;

        return new FieldProjectionChanges(uniqueValues: $unique, searchValues: $search, displayValue: $display);
    }

    public function supportedQueryOperators(): array
    {
        return self::COMMON_OPERATORS;
    }

    public function compileQuery(QueryPredicate $predicate): CompiledPredicate
    {
        $this->assertSupportedQueryOperator(
            $predicate,
            'unsupported_field_operator',
            "Operator '{$predicate->operator}' is not supported by '{$this->type()}'.",
        );

        return new CompiledPredicate('jsonb', $this->sqlCast());
    }

    abstract protected function normalizeOne(mixed $value, FieldDefinition $field, string $occurrence): mixed;

    abstract protected function validateOne(mixed $value, FieldDefinition $field, string $occurrence): void;

    protected function sqlCast(): ?string
    {
        return null;
    }

    protected function assertSupportedQueryOperator(
        QueryPredicate $predicate,
        string $reason,
        string $message,
    ): void {
        if (! in_array($predicate->operator, $this->supportedQueryOperators(), true)) {
            throw DataPlatformBadRequest::because($reason, $message, [
                'field_type' => $this->type(),
                'operator' => $predicate->operator,
            ]);
        }
    }

    protected function assertNonNegativeIntegerRange(
        FieldDefinition $field,
        string $minimum,
        ?string $maximum = null,
    ): void {
        $names = $maximum === null ? [$minimum] : [$minimum, $maximum];
        foreach ($names as $name) {
            $value = $field->constraints[$name] ?? null;
            if ($value !== null && (! is_int($value) || $value < 0)) {
                throw DataValidationException::one(
                    'invalid_schema_constraint',
                    "Constraint '{$name}' must be a non-negative integer.",
                    $field->path,
                );
            }
        }

        if ($maximum === null) {
            return;
        }
        $min = $field->constraints[$minimum] ?? null;
        $max = $field->constraints[$maximum] ?? null;
        if (is_int($min) && is_int($max) && $min > $max) {
            throw DataValidationException::one(
                'invalid_schema_constraint',
                "{$minimum} cannot exceed {$maximum}.",
                $field->path,
            );
        }
    }

    protected function itemOccurrence(FieldDefinition $field, string $occurrence, int|string $position): string
    {
        return $field->cardinality === Cardinality::MANY
            ? OccurrencePath::appendPosition($occurrence, $position)
            : $occurrence;
    }

    /** @return list<mixed> */
    protected function values(mixed $value, FieldDefinition $field): array
    {
        if ($value === null) {
            return [];
        }

        if ($field->cardinality === Cardinality::MANY) {
            if (! is_array($value) || ! array_is_list($value)) {
                throw DataValidationException::one(
                    'cardinality',
                    'Expected a list.',
                    $field->path,
                );
            }

            return array_values($value);
        }

        return [$value];
    }
}
