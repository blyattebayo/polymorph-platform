<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Support;

use Polymorph\Platform\SharedKernel\SystemFields\SystemFieldNames;
use Carbon\CarbonInterface;

final class RecordSystemFields
{
    private const CREATED_AT = 'created_at';
    private const UPDATED_AT = 'updated_at';
    private const DELETED_AT = 'deleted_at';

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function applyForCreate(array $payload, ?CarbonInterface $now = null): array
    {
        $timestamp = self::toIsoUtc($now ?? now());

        data_set($payload, self::fieldKey(self::CREATED_AT), $timestamp);
        data_set($payload, self::fieldKey(self::UPDATED_AT), $timestamp);
        data_set($payload, self::fieldKey(self::DELETED_AT), null);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    public static function applyForUpdate(array $payload, array $existing, ?CarbonInterface $now = null): array
    {
        $createdAt = self::timestamp($existing, self::CREATED_AT) ?? self::toIsoUtc($now ?? now());
        data_set($payload, self::fieldKey(self::CREATED_AT), $createdAt);

        $existingDeletedAt = self::timestamp($existing, self::DELETED_AT);
        if ($existingDeletedAt !== null) {
            data_set($payload, self::fieldKey(self::DELETED_AT), $existingDeletedAt);
        } elseif (data_get($payload, self::fieldKey(self::DELETED_AT)) !== null) {
            data_set($payload, self::fieldKey(self::DELETED_AT), null);
        }

        data_set($payload, self::fieldKey(self::UPDATED_AT), self::toIsoUtc($now ?? now()));

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function applyForDelete(array $payload, ?CarbonInterface $now = null): array
    {
        $timestamp = self::toIsoUtc($now ?? now());
        data_set($payload, self::fieldKey(self::UPDATED_AT), $timestamp);
        data_set($payload, self::fieldKey(self::DELETED_AT), $timestamp);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function applyForRestore(array $payload, ?CarbonInterface $now = null): array
    {
        data_set($payload, self::fieldKey(self::UPDATED_AT), self::toIsoUtc($now ?? now()));
        data_set($payload, self::fieldKey(self::DELETED_AT), null);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function normalizeForRead(array $payload): array
    {
        $createdAt = self::timestamp($payload, self::CREATED_AT);
        $updatedAt = self::timestamp($payload, self::UPDATED_AT);
        $deletedAt = self::timestamp($payload, self::DELETED_AT);

        if ($createdAt !== null) {
            data_set($payload, self::fieldKey(self::CREATED_AT), $createdAt);
        }

        if ($updatedAt !== null) {
            data_set($payload, self::fieldKey(self::UPDATED_AT), $updatedAt);
        }

        data_set($payload, self::fieldKey(self::DELETED_AT), $deletedAt);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function timestamp(array $payload, string $field): ?string
    {
        $value = data_get($payload, self::fieldKey($field));

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function isDeleted(array $payload): bool
    {
        return self::timestamp($payload, self::DELETED_AT) !== null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return string[]
     */
    public static function forbiddenPayloadSystemKeys(array $payload): array
    {
        $forbidden = [];

        foreach (array_keys($payload) as $key) {
            if (is_string($key) && SystemFieldNames::isReservedSystemPath($key)) {
                $forbidden[] = $key;
            }
        }

        return $forbidden;
    }

    private static function toIsoUtc(CarbonInterface $dateTime): string
    {
        return $dateTime->copy()->utc()->toIso8601String();
    }

    private static function fieldKey(string $field): string
    {
        return match ($field) {
            self::CREATED_AT => SystemFieldNames::CREATED_AT,
            self::UPDATED_AT => SystemFieldNames::UPDATED_AT,
            self::DELETED_AT => SystemFieldNames::DELETED_AT,
            default => '__' . ltrim($field, '_'),
        };
    }
}