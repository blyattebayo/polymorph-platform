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
        if (array_key_exists('shape', $field->constraints)) {
            throw DataValidationException::one('obsolete_schema_constraint', "JSON containers do not accept the obsolete 'shape' constraint.", $field->path);
        }
    }

    public function type(): string
    {
        return 'json';
    }

    protected function normalizeOne(mixed $value, FieldDefinition $field, string $occurrence): mixed
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw DataValidationException::one('container_type', 'Expected an object.', $field->path, $occurrence);
        }

        return $value;
    }

    protected function validateOne(mixed $value, FieldDefinition $field, string $occurrence): void
    {
        // Structural children and unknown keys are validated by SchemaDocumentProcessor.
    }

    /** @return list<string> */
    public function supportedQueryOperators(): array
    {
        return ['eq', 'contains', 'is_null', 'is_not_null'];
    }
}
