<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Support;

final class RecordDataNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public static function fromMixed(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
