<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение: email адрес уже используется.
 */
class UserAlreadyExistsException extends RuntimeException implements ErrorConvertible
{
    private ?string $email = null;

    /**
     * Создать исключение для email.
     */
    public static function withEmail(string $email): self
    {
        $exception = new self("User with email '{$email}' already exists");
        $exception->email = $email;

        return $exception;
    }

    /**
     * Конвертировать в ErrorPayload для API.
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::CONFLICT)
            ->detail($this->getMessage())
            ->meta([
                'resource' => 'user',
                'email' => $this->email,
            ])
            ->build();
    }
}
