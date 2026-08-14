<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Access;

final class DenyAllDataAccessPolicy implements DataAccessPolicy
{
    use UniformDataAccessPolicy;

    protected function grantsAllDataAccess(): bool
    {
        return false;
    }
}
