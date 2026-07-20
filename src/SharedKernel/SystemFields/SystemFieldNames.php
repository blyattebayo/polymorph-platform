<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\SystemFields;

final class SystemFieldNames
{
    public const CREATED_AT = '__created_at';
    public const UPDATED_AT = '__updated_at';
    public const DELETED_AT = '__deleted_at';

    /**
     * @return string[]
     */
    public static function writableKeys(): array
    {
        return [
            self::CREATED_AT,
            self::UPDATED_AT,
            self::DELETED_AT,
        ];
    }

    public static function isReservedSystemPath(string $fieldPath): bool
    {
        return str_starts_with($fieldPath, '__');
    }
}