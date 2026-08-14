<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

final class FloatFieldTypeHandler extends NumericFieldTypeHandler
{
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
        $this->validateRange($value, $field, $occurrence);
    }

    protected function sqlCast(): ?string
    {
        return 'numeric';
    }

    protected function validBoundary(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    protected function boundaryDescription(): string
    {
        return 'a finite number';
    }

    protected function castBoundary(mixed $value): float
    {
        return (float) $value;
    }
}
