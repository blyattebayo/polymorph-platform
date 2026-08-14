<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Serialization;

use JsonException;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;

/** Encodes and decodes JSON values at Data Platform persistence boundaries. */
final class DatabaseJson
{
    public function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $value */
    public function encodeNullableMap(array $value): ?string
    {
        return $value === [] ? null : $this->encode($value);
    }

    /** @return array<string, mixed> */
    public function decodeMap(mixed $value, string $column): array
    {
        $decoded = $this->decodeValue($value, $column);
        if ($decoded === null) {
            return [];
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw $this->invalidShape($column, 'object');
        }

        return $decoded;
    }

    /** @return list<mixed> */
    public function decodeList(mixed $value, string $column): array
    {
        $decoded = $this->decodeValue($value, $column);
        if ($decoded === null) {
            return [];
        }
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw $this->invalidShape($column, 'array');
        }

        return $decoded;
    }

    public function decodeValue(mixed $value, string $column): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        if (! is_string($value)) {
            throw $this->invalidShape($column, 'JSON');
        }

        try {
            return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw DataPlatformInvariantViolation::because(
                'invalid_stored_json',
                "Stored Data Platform column '{$column}' contains invalid JSON.",
                ['column' => $column],
                $exception,
            );
        }
    }

    private function invalidShape(string $column, string $expected): DataPlatformInvariantViolation
    {
        return DataPlatformInvariantViolation::because(
            'invalid_stored_json_shape',
            "Stored Data Platform column '{$column}' must contain a JSON {$expected}.",
            ['column' => $column, 'expected' => $expected],
        );
    }
}
