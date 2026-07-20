<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение: невалидный пароль (не соответствует требованиям).
 */
class InvalidPasswordException extends RuntimeException implements ErrorConvertible
{
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

    /**
     * Конвертировать в ErrorPayload для API.
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail($this->reason)
            ->meta([
                'field' => 'password',
                'reason' => 'invalid_format',
                'requirements' => $this->requirements,
            ])
            ->build();
    }
}
