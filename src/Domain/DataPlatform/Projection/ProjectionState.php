<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

enum ProjectionState: string
{
    case Pending = 'pending';
    case Applying = 'applying';
    case Applied = 'applied';
    case Failed = 'failed';
}
