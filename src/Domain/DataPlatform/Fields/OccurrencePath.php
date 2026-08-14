<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

/** Owns the bracket encoding shared by document occurrences and edge identity. */
final class OccurrencePath
{
    private const STABLE_ITEM_ID_PATTERN = '[0-9A-HJKMNP-TV-Z]{26}';

    public static function appendPosition(string $occurrence, int|string $position): string
    {
        return $occurrence.'['.$position.']';
    }

    public static function appendDocumentItem(
        string $occurrence,
        mixed $item,
        int|string $fallbackPosition,
    ): string {
        $identity = is_array($item) && is_string($item['_item_id'] ?? null)
            ? $item['_item_id']
            : $fallbackPosition;

        return self::appendPosition($occurrence, $identity);
    }

    public static function lastStableItemId(string $occurrence): ?string
    {
        preg_match_all('/\[('.self::STABLE_ITEM_ID_PATTERN.')\]/', $occurrence, $matches);
        $ids = $matches[1] ?? [];

        return $ids === [] ? null : (string) end($ids);
    }

    public static function isSameOrNestedItem(string $candidate, string $root): bool
    {
        return $candidate === $root || str_starts_with($candidate, $root.'[');
    }
}
