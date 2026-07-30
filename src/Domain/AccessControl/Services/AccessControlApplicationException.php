<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

final class AccessControlApplicationException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private function __construct(string $message, private readonly string $kind)
    {
        parent::__construct($message);
    }

    public static function validation(string $message): self
    {
        return new self($message, 'validation');
    }

    public static function notFound(string $message): self
    {
        return new self($message, 'not_found');
    }

    public static function conflict(string $message): self
    {
        return new self($message, 'conflict');
    }

    public function errorCode(): ErrorCode
    {
        return match ($this->kind) {
            'not_found' => ErrorCode::NOT_FOUND,
            'conflict' => ErrorCode::CONFLICT,
            default => ErrorCode::VALIDATION_ERROR,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [];
    }
}
