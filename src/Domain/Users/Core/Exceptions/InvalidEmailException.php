<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение: невалидный email адрес.
 */
class InvalidEmailException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        public readonly string $email,
        public readonly string $reason = 'Invalid email format'
    ) {
        parent::__construct("Invalid email address: {$email}");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::VALIDATION_ERROR;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [
            'field' => 'email',
            'value' => $this->email,
            'reason' => 'invalid_format',
        ];
    }

    protected function errorDetail(): string
    {
        return $this->reason;
    }
}
