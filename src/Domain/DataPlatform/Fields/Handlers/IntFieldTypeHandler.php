<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

final class IntFieldTypeHandler extends NumericFieldTypeHandler
{
    public function type(): string
    {
        return 'int';
    }

    protected function normalizeOne(mixed $value, FieldDefinition $field, string $occurrence): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1) {
            return (int) $value;
        }

        throw DataValidationException::one('type', 'Expected an integer.', $field->path, $occurrence);
    }

    protected function validateOne(mixed $value, FieldDefinition $field, string $occurrence): void
    {
        if (! is_int($value)) {
            throw DataValidationException::one('type', 'Expected an integer.', $field->path, $occurrence);
        }
        $this->validateRange($value, $field, $occurrence);
    }

    protected function sqlCast(): ?string
    {
        return 'bigint';
    }

    protected function validBoundary(mixed $value): bool
    {
        return is_int($value);
    }

    protected function boundaryDescription(): string
    {
        return 'an integer';
    }

    protected function castBoundary(mixed $value): int
    {
        return (int) $value;
    }
}
