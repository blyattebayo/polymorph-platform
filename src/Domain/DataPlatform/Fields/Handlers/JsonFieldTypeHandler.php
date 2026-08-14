<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

final class JsonFieldTypeHandler extends AbstractFieldTypeHandler
{
    public function validateSchema(FieldDefinition $field): void
    {
        parent::validateSchema($field);
        $shape = $field->constraints['shape'] ?? null;
        if ($shape !== null && ! in_array($shape, ['object', 'array'], true)) {
            throw DataValidationException::one('invalid_schema_constraint', "Constraint 'shape' must be object or array.", $field->path);
        }
    }

    public function type(): string
    {
        return 'json';
    }

    protected function normalizeOne(mixed $value, FieldDefinition $field, string $occurrence): mixed
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw DataValidationException::one('json', 'Value is not JSON serializable.', $field->path, $occurrence);
        }

        return $value;
    }

    protected function validateOne(mixed $value, FieldDefinition $field, string $occurrence): void
    {
        $shape = $field->constraints['shape'] ?? null;
        if ($shape === 'object' && (! is_array($value) || array_is_list($value))) {
            throw DataValidationException::one('shape', 'Expected a JSON object.', $field->path, $occurrence);
        }
        if ($shape === 'array' && (! is_array($value) || ! array_is_list($value))) {
            throw DataValidationException::one('shape', 'Expected a JSON array.', $field->path, $occurrence);
        }
    }

    /** @return list<string> */
    public function supportedQueryOperators(): array
    {
        return ['eq', 'contains', 'is_null', 'is_not_null'];
    }
}
