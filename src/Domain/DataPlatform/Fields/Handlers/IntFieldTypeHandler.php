<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

final class IntFieldTypeHandler extends AbstractFieldTypeHandler
{
    public function validateSchema(FieldDefinition $field): void
    {
        parent::validateSchema($field);
        $min = $field->constraints['min'] ?? null;
        $max = $field->constraints['max'] ?? null;
        foreach (['min' => $min, 'max' => $max] as $name => $value) {
            if ($value !== null && ! is_int($value)) {
                throw DataValidationException::one('invalid_schema_constraint', "Constraint '{$name}' must be an integer.", $field->path);
            }
        }
        if (is_int($min) && is_int($max) && $min > $max) {
            throw DataValidationException::one('invalid_schema_constraint', 'min cannot exceed max.', $field->path);
        }
    }

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

    /** @return list<string> */
    public function supportedQueryOperators(): array
    {
        return ['eq', 'in', 'lt', 'lte', 'gt', 'gte', 'between', 'is_null', 'is_not_null'];
    }

    protected function sqlCast(): ?string
    {
        return 'bigint';
    }

    private function validateRange(int $value, FieldDefinition $field, string $occurrence): void
    {
        $min = $field->constraints['min'] ?? null;
        $max = $field->constraints['max'] ?? null;
        if (is_numeric($min) && $value < (int) $min) {
            throw DataValidationException::one('min', "Value must be at least {$min}.", $field->path, $occurrence);
        }
        if (is_numeric($max) && $value > (int) $max) {
            throw DataValidationException::one('max', "Value must be at most {$max}.", $field->path, $occurrence);
        }
    }
}
