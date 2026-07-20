<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Policies;

enum SessionRotationDecision
{
    case Missing;
    case Reused;
    case Expired;
    case Allowed;
}
