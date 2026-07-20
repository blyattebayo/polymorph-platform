<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

interface AccessSubjectProvider
{
    /**
     * @return list<\Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject>
     */
    public function for(UserIdentity $user): array;
}
