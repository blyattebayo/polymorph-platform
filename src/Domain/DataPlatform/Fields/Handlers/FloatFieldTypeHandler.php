<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

final class FloatFieldTypeHandler extends AbstractFieldTypeHandler
{
    public function validateSchema(FieldDefinition $field): void
    {
        parent::validateSchema($field);
        $min = $field->constraints['min'] ?? null;
        $max = $field->constraints['max'] ?? null;
        foreach (['min' => $min, 'max' => $max] as $name => $value) {
            if ($value !== null && (! is_int($value) && ! is_float($value) || ! is_finite((float) $value))) {
                throw DataValidationException::one('invalid_schema_constraint', "Constraint '{$name}' must be a finite number.", $field->path);
            }
        }
        if (is_numeric($min) && is_numeric($max) && (float) $min > (float) $max) {
            throw DataValidationException::one('invalid_schema_constraint', 'min cannot exceed max.', $field->path);
        }
    }

    public function type(): string
    {
        return 'float';
    }

    protected function normalizeOne(mixed $value, FieldDefinition $field, string $occurrence): float
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            throw DataValidationException::one('type', 'Expected a finite number.', $field->path, $occurrence);
        }
        $normalized = (float) $value;
        if (! is_finite($normalized)) {
            throw DataValidationException::one('finite', 'Expected a finite number.', $field->path, $occurrence);
        }

        return $normalized;
    }

    protected function validateOne(mixed $value, FieldDefinition $field, string $occurrence): void
    {
        if (! is_float($value) || ! is_finite($value)) {
            throw DataValidationException::one('type', 'Expected a finite number.', $field->path, $occurrence);
        }
        $min = $field->constraints['min'] ?? null;
        $max = $field->constraints['max'] ?? null;
        if (is_numeric($min) && $value < (float) $min) {
            throw DataValidationException::one('min', "Value must be at least {$min}.", $field->path, $occurrence);
        }
        if (is_numeric($max) && $value > (float) $max) {
            throw DataValidationException::one('max', "Value must be at most {$max}.", $field->path, $occurrence);
        }
    }

    /** @return list<string> */
    public function supportedQueryOperators(): array
    {
        return ['eq', 'in', 'lt', 'lte', 'gt', 'gte', 'between', 'is_null', 'is_not_null'];
    }

    protected function sqlCast(): ?string
    {
        return 'numeric';
    }
}
