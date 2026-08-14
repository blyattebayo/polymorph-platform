<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Errors;

use LogicException;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class DataPlatformStateConflict extends LogicException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;
    use HasReasonedError;

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }
}
