<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Contracts;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
