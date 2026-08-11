<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\Users\Core\Models\User;

interface AccessSubjectProvider
{
    /**
     * @return list<Subject>
     */
    public function for(User $user): array;
}
