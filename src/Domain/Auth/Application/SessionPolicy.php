<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application;

final class SessionPolicy
{
    public const LIFETIME_SECONDS = 30 * 24 * 60 * 60;

    public const MAX_ACTIVE_PER_USER = 20;

    private function __construct() {}
}
