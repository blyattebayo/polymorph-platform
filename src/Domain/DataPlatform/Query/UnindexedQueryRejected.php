<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

use Polymorph\Platform\SharedKernel\Contracts\DescribesErrorReport;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorReport;

final class UnindexedQueryRejected extends \RuntimeException implements DescribesErrorReport, DomainErrorDescriptor, ErrorConvertible
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

    protected function errorDetail(): string
    {
        return 'The query requires an indexed execution path that is not available.';
    }

    public function errorReport(ErrorPayload $payload): ErrorReport
    {
        return new ErrorReport(
            level: 'warning',
            message: 'Data Platform query rejected because an indexed execution path was unavailable',
            context: ['diagnostic' => $this->getMessage()],
        );
    }
}
