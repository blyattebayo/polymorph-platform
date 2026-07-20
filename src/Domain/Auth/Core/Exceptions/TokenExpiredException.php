<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение: срок действия токена истек.
 */
class TokenExpiredException extends RuntimeException implements ErrorConvertible
{
    /**
     * Создать исключение для истекшего токена.
     */
    public static function expired(): self
    {
        return new self('Token has expired');
    }

    /**
     * Конвертировать в ErrorPayload для API.
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::UNAUTHORIZED)
            ->detail($this->getMessage())
            ->meta([
                'reason' => 'token_expired',
                'detail' => 'Please refresh your authentication token',
            ])
            ->build();
    }
}
