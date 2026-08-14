<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

final class BoolFieldTypeHandler extends AbstractFieldTypeHandler
{
    public function type(): string
    {
        return 'bool';
    }

    protected function normalizeOne(mixed $value, FieldDefinition $field, string $occurrence): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (in_array($value, [0, 1, '0', '1'], true)) {
            return (bool) $value;
        }

        throw DataValidationException::one('type', 'Expected a boolean.', $field->path, $occurrence);
    }

    protected function validateOne(mixed $value, FieldDefinition $field, string $occurrence): void
    {
        if (! is_bool($value)) {
            throw DataValidationException::one('type', 'Expected a boolean.', $field->path, $occurrence);
        }
    }

    protected function sqlCast(): ?string
    {
        return 'boolean';
    }
}
