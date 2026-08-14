<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

class StringFieldTypeHandler extends AbstractFieldTypeHandler
{
    public function validateSchema(FieldDefinition $field): void
    {
        parent::validateSchema($field);
        $this->assertNonNegativeIntegerRange($field, 'min_length', 'max_length');
        if (array_key_exists('trim', $field->constraints) && ! is_bool($field->constraints['trim'])) {
            throw DataValidationException::one('invalid_schema_constraint', "Constraint 'trim' must be boolean.", $field->path);
        }
        $pattern = $field->constraints['pattern'] ?? null;
        if ($pattern !== null && (! is_string($pattern) || @preg_match($pattern, '') === false)) {
            throw DataValidationException::one('schema_pattern', 'Field pattern is invalid.', $field->path);
        }
        $enum = $field->constraints['enum'] ?? null;
        if ($enum !== null && (! is_array($enum)
            || array_filter($enum, static fn (mixed $value): bool => ! is_string($value)) !== []
            || count(array_unique($enum)) !== count($enum))) {
            throw DataValidationException::one('invalid_schema_constraint', 'String enum must contain unique strings.', $field->path);
        }
    }

    public function type(): string
    {
        return 'string';
    }

    protected function normalizeOne(mixed $value, FieldDefinition $field, string $occurrence): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            throw DataValidationException::one('type', 'Expected a string.', $field->path, $occurrence);
        }

        return ($field->constraints['trim'] ?? true) === true ? trim((string) $value) : (string) $value;
    }

    protected function validateOne(mixed $value, FieldDefinition $field, string $occurrence): void
    {
        if (! is_string($value)) {
            throw DataValidationException::one('type', 'Expected a string.', $field->path, $occurrence);
        }

        $length = mb_strlen($value);
        $min = $field->constraints['min_length'] ?? null;
        $max = $field->constraints['max_length'] ?? null;
        if (is_int($min) && $length < $min) {
            throw DataValidationException::one('min_length', "Minimum length is {$min}.", $field->path, $occurrence);
        }
        if (is_int($max) && $length > $max) {
            throw DataValidationException::one('max_length', "Maximum length is {$max}.", $field->path, $occurrence);
        }

        $pattern = $field->constraints['pattern'] ?? null;
        if (is_string($pattern) && preg_match($pattern, $value) !== 1) {
            throw DataValidationException::one('pattern', 'Value does not match the required pattern.', $field->path, $occurrence);
        }

        $allowed = $field->constraints['enum'] ?? null;
        if (is_array($allowed) && ! in_array($value, $allowed, true)) {
            throw DataValidationException::one('enum', 'Value is not in the allowed set.', $field->path, $occurrence);
        }
    }

    /** @return list<string> */
    public function supportedQueryOperators(): array
    {
        return ['eq', 'in', 'is_null', 'is_not_null', 'contains', 'starts_with'];
    }
}
