<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

enum IdempotencyState: string
{
    case Processing = 'processing';
    case Completed = 'completed';
}
