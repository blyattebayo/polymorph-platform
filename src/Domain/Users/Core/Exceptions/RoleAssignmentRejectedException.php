<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class RoleAssignmentRejectedException extends \DomainException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return ['resource' => 'user_role_assignment'];
    }
}
