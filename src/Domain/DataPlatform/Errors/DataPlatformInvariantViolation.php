<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Errors;

use LogicException;
use Polymorph\Platform\SharedKernel\Contracts\DescribesErrorReport;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorReport;

final class DataPlatformInvariantViolation extends LogicException implements DescribesErrorReport, DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    /** @param array<string, mixed> $context */
    private function __construct(
        public readonly string $reason,
        string $message,
        private readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /** @param array<string, mixed> $context */
    public static function because(string $reason, string $message, array $context = [], ?\Throwable $previous = null): self
    {
        return new self($reason, $message, $context, $previous);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::INTERNAL_SERVER_ERROR;
    }

    /** @return array<string, mixed> */
    public function errorMeta(): array
    {
        return ['reason' => $this->reason];
    }

    protected function errorDetail(): string
    {
        return 'The data platform encountered an invalid internal state.';
    }

    public function errorReport(ErrorPayload $payload): ErrorReport
    {
        // ErrorReportPolicy may inspect an exception instance created without
        // its constructor, so promoted diagnostics must remain defensive.
        $reason = isset($this->reason) ? $this->reason : 'unknown_invariant';
        $context = isset($this->context) ? $this->context : [];

        return new ErrorReport(
            level: 'error',
            message: 'Data platform invariant violation',
            context: ['reason' => $reason, 'detail' => $this->getMessage(), ...$context],
        );
    }
}
