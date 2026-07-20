<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Ownership;

enum OwnerType: string
{
    case SYSTEM = 'system';
    case PLATFORM = 'platform';
    case PLUGIN = 'plugin';
}
