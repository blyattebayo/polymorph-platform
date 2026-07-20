<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

interface AccessSubjectProvider
{
    /**
     * @return list<Subject>
     */
    public function for(UserIdentity $user): array;
}
