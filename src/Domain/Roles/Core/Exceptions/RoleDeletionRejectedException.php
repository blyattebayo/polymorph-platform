<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

final class RoleDeletionRejectedException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    /**
     * @param  array<string, mixed>  $meta
     */
    private function __construct(
        string $message,
        private readonly array $meta = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $violation
     */
    public static function fromViolation(array $violation): self
    {
        return new self(
            (string) ($violation['detail'] ?? 'Role cannot be deleted.'),
            array_merge(['resource' => 'role'], $violation),
        );
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return $this->meta;
    }
}
