<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

/** Shared schema and value-range contract for integer and floating fields. */
abstract class NumericFieldTypeHandler extends ComparableFieldTypeHandler
{
    final public function validateSchema(FieldDefinition $field): void
    {
        parent::validateSchema($field);
        $min = $field->constraints['min'] ?? null;
        $max = $field->constraints['max'] ?? null;
        foreach (['min' => $min, 'max' => $max] as $name => $value) {
            if ($value !== null && ! $this->validBoundary($value)) {
                throw DataValidationException::one(
                    'invalid_schema_constraint',
                    "Constraint '{$name}' must be {$this->boundaryDescription()}.",
                    $field->path,
                );
            }
        }
        if ($min !== null && $max !== null
            && $this->validBoundary($min) && $this->validBoundary($max)
            && $this->castBoundary($min) > $this->castBoundary($max)) {
            throw DataValidationException::one('invalid_schema_constraint', 'min cannot exceed max.', $field->path);
        }
    }

    protected function validateRange(int|float $value, FieldDefinition $field, string $occurrence): void
    {
        $min = $field->constraints['min'] ?? null;
        $max = $field->constraints['max'] ?? null;
        if (is_numeric($min) && $value < $this->castBoundary($min)) {
            throw DataValidationException::one('min', "Value must be at least {$min}.", $field->path, $occurrence);
        }
        if (is_numeric($max) && $value > $this->castBoundary($max)) {
            throw DataValidationException::one('max', "Value must be at most {$max}.", $field->path, $occurrence);
        }
    }

    abstract protected function validBoundary(mixed $value): bool;

    abstract protected function boundaryDescription(): string;

    abstract protected function castBoundary(mixed $value): int|float;
}
