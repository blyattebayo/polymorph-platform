<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

final class ProjectionEdgeIdentity
{
    private static function itemId(string $occurrence): ?string
    {
        preg_match_all('/\[([0-9A-HJKMNP-TV-Z]{26})\]/', $occurrence, $matches);
        $ids = $matches[1] ?? [];

        return $ids === [] ? null : (string) end($ids);
    }

    /** @param array<string,mixed> $edge @return array<string,mixed> */
    public static function withItemId(array $edge): array
    {
        $edge['item_id'] = self::itemId((string) ($edge['occurrence'] ?? ''));

        return $edge;
    }
}
