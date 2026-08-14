<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

/** Explicit arbitrary JSON value; never participates in schema traversal. */
final class RawJsonFieldTypeHandler extends AbstractFieldTypeHandler
{
    public function type(): string
    {
        return 'raw_json';
    }

    protected function normalizeOne(mixed $value, FieldDefinition $field, string $occurrence): mixed
    {
        try {
            $this->canonicalJson->encode($value);
        } catch (\JsonException) {
            throw DataValidationException::one('json', 'Value is not JSON serializable.', $field->path, $occurrence);
        }

        return $value;
    }

    protected function validateOne(mixed $value, FieldDefinition $field, string $occurrence): void {}

    /** @return list<string> */
    public function supportedQueryOperators(): array
    {
        return ['eq', 'contains', 'is_null', 'is_not_null'];
    }
}
