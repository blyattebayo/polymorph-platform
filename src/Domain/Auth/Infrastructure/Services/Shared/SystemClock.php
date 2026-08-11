<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared;

use DateTimeImmutable;
use Illuminate\Support\Facades\Date;
use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return Date::now('UTC')->toDateTimeImmutable();
    }
}
