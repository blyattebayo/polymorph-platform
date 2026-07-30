<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение: срок действия токена истек.
 */
class TokenExpiredException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    /**
     * Создать исключение для истекшего токена.
     */
    public static function expired(): self
    {
        return new self('Token has expired');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::UNAUTHORIZED;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        // Ключ hint, а не detail: в payload уже есть верхнеуровневый detail,
        // и второй одноимённый ключ внутри meta читался как его дубль.
        return [
            'reason' => 'token_expired',
            'hint' => 'Please refresh your authentication token',
        ];
    }
}
