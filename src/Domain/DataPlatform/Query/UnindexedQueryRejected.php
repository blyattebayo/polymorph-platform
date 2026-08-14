<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class UnindexedQueryRejected extends \RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function errorCode(): ErrorCode
    {
        return ErrorCode::BAD_REQUEST;
    }

    public function errorMeta(): array
    {
        return ['reason' => 'unindexed_query_rejected'];
    }
}
