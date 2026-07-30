<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DescribesErrorReport;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorReport;
use RuntimeException;

/**
 * Исключение: ошибка конфигурации JWT (отсутствует secret, неверные параметры).
 */
class JwtConfigurationException extends RuntimeException implements DescribesErrorReport, DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    /**
     * Создать исключение для отсутствующего secret key.
     */
    public static function missingSecret(): self
    {
        return new self('JWT secret key is not configured. Set JWT_SECRET in .env');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::INTERNAL_SERVER_ERROR;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [];
    }

    protected function errorDetail(): string
    {
        return 'Service configuration error';
    }

    /**
     * Клиенту это обычные 500, но для оператора — critical: пока secret не настроен,
     * сервис не выпустит ни один токен, то есть аутентификация мертва целиком.
     * Сообщение исключения диагностическое и в ответ не идёт, поэтому кладём его в лог.
     */
    public function errorReport(ErrorPayload $payload): ErrorReport
    {
        return new ErrorReport(
            level: 'critical',
            message: 'JWT configuration error detected',
            context: [
                'reason' => $this->getMessage(),
                'suggestion' => 'Check .env file for JWT_SECRET configuration',
            ],
        );
    }
}
