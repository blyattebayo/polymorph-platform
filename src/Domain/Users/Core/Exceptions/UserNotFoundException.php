<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение: пользователь не найден.
 */
class UserNotFoundException extends RuntimeException implements ErrorConvertible
{
    private ?int $userId = null;

    private ?string $email = null;

    /**
     * Создать исключение для ID.
     */
    public static function byId(int $id): self
    {
        $exception = new self("User with ID '{$id}' not found");
        $exception->userId = $id;

        return $exception;
    }

    /**
     * Создать исключение для email.
     */
    public static function byEmail(string $email): self
    {
        $exception = new self("User with email '{$email}' not found");
        $exception->email = $email;

        return $exception;
    }

    /**
     * Конвертировать в ErrorPayload для API.
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::NOT_FOUND)
            ->detail($this->getMessage())
            ->meta([
                'resource' => 'user',
                'user_id' => $this->userId,
                'email' => $this->email,
            ])
            ->build();
    }
}
