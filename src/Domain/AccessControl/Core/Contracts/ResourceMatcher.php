<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

interface ResourceMatcher
{
    public function matches(string $pattern, string $resource): bool;
}
