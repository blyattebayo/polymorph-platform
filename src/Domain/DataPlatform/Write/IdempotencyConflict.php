<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class IdempotencyConflict extends \RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    public function errorMeta(): array
    {
        return ['reason' => 'idempotency_conflict'];
    }
}
