<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\ValueObjects;

enum Effect: string
{
    case ALLOW = 'allow';
    case DENY = 'deny';

    public function isAllow(): bool
    {
        return $this === self::ALLOW;
    }

    public function isDeny(): bool
    {
        return $this === self::DENY;
    }
}
