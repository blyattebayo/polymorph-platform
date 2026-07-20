<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Support;

/**
 * Детерминированные читаемые имена partial-индексов records (лимит Postgres: 63 символа).
 *
 * Хэш-суффикс считается от пути поля И каста: смена типа поля (bigint→numeric)
 * меняет имя индекса, поэтому реконсайл (diff по имени) дропает устаревший индекс
 * и создаёт корректный. Без каста в сигнатуре stale-индекс с неверным выражением
 * молча оставался бы навсегда.
 */
final class RecordIndexNameBuilder
{
    private const MAX_IDENTIFIER_LENGTH = 63;

    private const HASH_LENGTH = 16;

    public static function fieldIndex(int $definitionId, string $fieldPath, ?string $cast): string
    {
        return self::build('idx_recf', $definitionId, $fieldPath, $cast);
    }

    public static function uniqueIndex(int $definitionId, string $fieldPath, ?string $cast): string
    {
        return self::build('uq_recf', $definitionId, $fieldPath, $cast);
    }

    private static function build(string $prefix, int $definitionId, string $fieldPath, ?string $cast): string
    {
        $hash = substr(md5($fieldPath.'|'.($cast ?? '')), 0, self::HASH_LENGTH);
        $base = "{$prefix}_{$definitionId}_";
        $slug = strtolower($fieldPath);
        $slug = preg_replace('/[^a-z0-9_]/', '_', $slug) ?? $slug;
        $slug = trim((string) $slug, '_');
        if ($slug === '') {
            $slug = 'field';
        }

        $maxSlugLength = self::MAX_IDENTIFIER_LENGTH - strlen($base) - 1 - strlen($hash);
        if ($maxSlugLength < 1) {
            $maxSlugLength = 1;
        }

        $slug = substr($slug, 0, $maxSlugLength);

        return $base.$slug.'_'.$hash;
    }
}
