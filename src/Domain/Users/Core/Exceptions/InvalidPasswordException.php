<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение: невалидный пароль (не соответствует требованиям).
 */
class InvalidPasswordException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        public readonly string $reason,
        public readonly array $requirements = []
    ) {
        parent::__construct("Invalid password: {$reason}");
    }

    /**
     * Создать исключение для слишком короткого пароля.
     */
    public static function tooShort(int $minLength): self
    {
        return new self(
            "Password must be at least {$minLength} characters long",
            ['min_length' => $minLength]
        );
    }

    /**
     * Создать исключение для слишком длинного пароля.
     */
    public static function tooLong(int $maxLength): self
    {
        return new self(
            "Password must not exceed {$maxLength} characters",
            ['max_length' => $maxLength]
        );
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
            'field' => 'password',
            'reason' => 'invalid_format',
            'requirements' => $this->requirements,
        ];
    }

    protected function errorDetail(): string
    {
        return $this->reason;
    }
}
