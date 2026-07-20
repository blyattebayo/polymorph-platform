<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Support;

final class RoleIdsNormalizer
{
    /**
     * @return list<int>
     */
    public static function normalize(mixed $roleIds): array
    {
        if (! is_array($roleIds) || $roleIds === []) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (mixed $roleId): int => (int) $roleId,
            $roleIds,
        )));
    }
}
