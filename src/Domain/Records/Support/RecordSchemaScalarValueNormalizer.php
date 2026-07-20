<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Support;

use Illuminate\Support\Carbon;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;

/**
 * Каноническая нормализация скалярных schema-значений (write + query).
 */
final class RecordSchemaScalarValueNormalizer
{
    /** ISO-8601 с обязательным timezone (Z или ±HH:MM). */
    public const DATETIME_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/';

    public function normalize(FieldType $type, mixed $value): mixed
    {
        return match ($type) {
            FieldType::INT, FieldType::REF => (int) $value,
            FieldType::FLOAT => (float) $value,
            FieldType::BOOL => $this->normalizeBool($value),
            FieldType::DATETIME => $this->normalizeDateTime($value),
            default => $value,
        };
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? (bool) $value;
    }

    public function normalizeDateTime(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new \InvalidArgumentException('Datetime value must be a parseable scalar.');
        }

        $stringValue = (string) $value;
        if (preg_match(self::DATETIME_PATTERN, $stringValue) !== 1) {
            throw new \InvalidArgumentException('Datetime value must be ISO-8601 with timezone.');
        }

        return Carbon::parse($stringValue)->utc()->toIso8601String();
    }
}
