<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Query;

use Polymorph\Platform\Support\Validation\ValidationConstraints;

/**
 * Единый источник SQL-выражений по jsonb-полям для query engine и materializer.
 */
final class RecordFieldSqlExpression
{
    public static function safeJsonKey(string $key): string
    {
        if (!ValidationConstraints::slug()->matches($key)) {
            throw new \InvalidArgumentException("Unsafe record field key '{$key}'.");
        }

        return $key;
    }

    /** Выражение для WHERE/ORDER BY (совпадает с partial expression index). */
    public static function fieldExpression(string $key, ?string $cast): string
    {
        $key = self::safeJsonKey($key);

        return $cast === null
            ? "(data_json ->> '{$key}')"
            : "((data_json ->> '{$key}')::{$cast})";
    }

    /** Выражение для CREATE INDEX ON records (Postgres требует скобки вокруг expression). */
    public static function indexExpression(string $key, ?string $cast): string
    {
        $key = self::safeJsonKey($key);

        return $cast === null
            ? "((data_json ->> '{$key}'))"
            : "(((data_json ->> '{$key}')::{$cast}))";
    }

    /** Tolerant numeric cast для aggregate по legacy dirty data. */
    public static function safeNumericExpression(string $key): string
    {
        $key = self::safeJsonKey($key);

        return "CASE WHEN (data_json ->> '{$key}') ~ '^-?[0-9]+(\\.[0-9]+)?$' "
            . "THEN (data_json ->> '{$key}')::numeric ELSE NULL END";
    }
}
