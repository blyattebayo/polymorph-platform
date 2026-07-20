<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение: ошибка конфигурации JWT (отсутствует secret, неверные параметры).
 */
class JwtConfigurationException extends RuntimeException implements ErrorConvertible
{
    /**
     * Создать исключение для отсутствующего secret key.
     */
    public static function missingSecret(): self
    {
        return new self('JWT secret key is not configured. Set JWT_SECRET in .env');
    }

    /**
     * Конвертировать в ErrorPayload для API.
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::INTERNAL_SERVER_ERROR)
            ->detail('Service configuration error')
            ->build();
    }
}
