<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\ResourceMatcher;

final class DotPrefixResourceMatcher implements ResourceMatcher
{
    public function matches(string $pattern, string $resource): bool
    {
        $normalizedPattern = trim($pattern);
        $normalizedResource = trim($resource);

        if ($normalizedPattern === '' || $normalizedResource === '') {
            return false;
        }

        if ($normalizedPattern === '*' || $normalizedPattern === $normalizedResource) {
            return true;
        }

        return str_starts_with($normalizedResource, $normalizedPattern.'.');
    }
}
