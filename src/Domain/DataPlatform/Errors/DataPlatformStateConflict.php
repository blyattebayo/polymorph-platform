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

    /** @param array<string, mixed> $meta */
    private function __construct(
        public readonly string $reason,
        string $message,
        private readonly array $meta = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /** @param array<string, mixed> $meta */
    public static function because(string $reason, string $message, array $meta = [], ?\Throwable $previous = null): self
    {
        return new self($reason, $message, $meta, $previous);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    /** @return array<string, mixed> */
    public function errorMeta(): array
    {
        return ['reason' => $this->reason, ...$this->meta];
    }
}
