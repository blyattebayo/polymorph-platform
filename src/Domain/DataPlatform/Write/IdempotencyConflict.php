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

    private function __construct(
        public readonly string $reason,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function reused(): self
    {
        return new self('idempotency_key_reused', 'The idempotency key was already used with a different command payload.');
    }

    public static function inProgress(): self
    {
        return new self('idempotency_in_progress', 'The idempotent command is already processing.');
    }

    public static function raced(?\Throwable $previous = null): self
    {
        return new self('idempotency_race', 'The idempotent command raced with another request.', $previous);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    public function errorMeta(): array
    {
        return ['reason' => $this->reason];
    }
}
