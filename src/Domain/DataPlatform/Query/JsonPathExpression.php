<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;

/** Builds the canonical PostgreSQL expressions used by plans and expression indexes. */
final class JsonPathExpression
{
    public function text(FieldDefinition|string $field, string $column = 'r.data'): string
    {
        $path = $field instanceof FieldDefinition ? $field->path : $field;
        $segments = array_map(
            static fn (string $segment): string => "'".str_replace("'", "''", $segment)."'",
            explode('.', $path),
        );

        return "jsonb_extract_path_text({$column}, ".implode(', ', $segments).')';
    }

    public function cast(string $type): ?string
    {
        return match ($type) {
            'int' => 'bigint',
            'float' => 'double precision',
            'bool' => 'boolean',
            'datetime' => 'timestamptz',
            default => null,
        };
    }

    public function jsonPath(FieldDefinition|string $field): string
    {
        $path = $field instanceof FieldDefinition ? $field->path : $field;
        $segments = array_map(
            static fn (string $segment): string => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $segment).'"',
            explode('.', $path),
        );
        $jsonPath = '$.'.implode('.', $segments);

        return "'".str_replace("'", "''", $jsonPath)."'";
    }
}
