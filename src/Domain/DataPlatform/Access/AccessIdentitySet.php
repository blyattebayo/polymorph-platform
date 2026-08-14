<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Access;

/** Canonical normalization for batched record and media ACL identities. */
final class AccessIdentitySet
{
    /** @return list<int> */
    public static function positiveInts(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $values),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /** @return list<string> */
    public static function nonEmptyStrings(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map('strval', $values),
            static fn (string $id): bool => $id !== '',
        )));
    }
}
