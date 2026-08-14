<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Errors;

/** Shared constructor and metadata contract for reasoned generic failures. */
trait HasReasonedError
{
    /** @param array<string,mixed> $meta */
    private function __construct(
        public readonly string $reason,
        string $message,
        private readonly array $meta = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /** @param array<string,mixed> $meta */
    public static function because(
        string $reason,
        string $message,
        array $meta = [],
        ?\Throwable $previous = null,
    ): self {
        return new self($reason, $message, $meta, $previous);
    }

    /** @return array<string,mixed> */
    public function errorMeta(): array
    {
        return ['reason' => $this->reason, ...$this->meta];
    }
}
