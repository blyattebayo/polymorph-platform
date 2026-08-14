<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldType;

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

    public function cast(FieldType|string $type): ?string
    {
        $type = $type instanceof FieldType ? $type : FieldType::tryFrom($type);

        return match ($type) {
            FieldType::INT => 'bigint',
            FieldType::FLOAT => 'double precision',
            FieldType::BOOL => 'boolean',
            FieldType::DATETIME => 'timestamptz',
            default => null,
        };
    }

    public function jsonPath(FieldDefinition|string $field): string
    {
        $path = $field instanceof FieldDefinition ? $field->path : $field;
        $collectionPaths = $field instanceof FieldDefinition ? array_fill_keys($field->collectionPaths, true) : [];
        $prefix = [];
        $segments = [];
        foreach (explode('.', $path) as $segment) {
            $prefix[] = $segment;
            $encoded = '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $segment).'"';
            if (isset($collectionPaths[implode('.', $prefix)])) {
                $encoded .= '[*]';
            }
            $segments[] = $encoded;
        }
        $jsonPath = '$.'.implode('.', $segments);

        return "'".str_replace("'", "''", $jsonPath)."'";
    }
}
