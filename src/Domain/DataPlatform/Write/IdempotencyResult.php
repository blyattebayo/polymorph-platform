<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

interface IdempotencyResult
{
    /** @return array<string,mixed> */
    public function toArray(): array;
}
