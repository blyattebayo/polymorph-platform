<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

final class AuthSessionUnauthorizedException extends RuntimeException implements ErrorConvertible
{
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::UNAUTHORIZED)
            ->detail($this->getMessage())
            ->build();
    }
}
