<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;

final class RoleAssignmentRejectedException extends \DomainException implements ErrorConvertible
{
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::CONFLICT)
            ->detail($this->getMessage())
            ->meta(['resource' => 'user_role_assignment'])
            ->build();
    }
}
