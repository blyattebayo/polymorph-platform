<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Support;

use Polymorph\Platform\Domain\Records\Query\RecordFieldSqlExpression;

/**
 * Канонический root key payload записи: API request/response, Laravel validation paths
 * и имя колонки Eloquent `records.data_json`.
 *
 * Для SQL-выражений по колонке используйте {@see RecordFieldSqlExpression}.
 */
final class RecordPayloadPathRegistry
{
    public const ROOT_KEY = 'data_json';

    public static function rootPath(?string $suffix = null): string
    {
        if ($suffix === null || $suffix === '') {
            return self::ROOT_KEY;
        }

        return self::ROOT_KEY.'.'.ltrim($suffix, '.');
    }

    public static function dottedPrefix(): string
    {
        return self::ROOT_KEY.'.';
    }

    public static function hasDottedPrefix(string $path): bool
    {
        return str_starts_with($path, self::dottedPrefix());
    }

    public static function stripDottedPrefix(string $path): string
    {
        return ltrim(self::hasDottedPrefix($path)
            ? substr($path, strlen(self::dottedPrefix()))
            : $path, '.');
    }
}
