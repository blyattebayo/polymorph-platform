<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение: управление персональными токенами доступно только интерактивной сессии.
 *
 * Описывает себя само, а не полагается на таблицу маппингов. Пока маппинг лежал в
 * config/errors.php, исключение отдавало 403 со своим сообщением; после переноса
 * решений в код ветви для него не оказалось, и оно проваливалось в подстраховочный
 * `RuntimeException` — то есть 500 с потерянным текстом. Наружу это не выходило
 * только потому, что на всех соответствующих маршрутах раньше срабатывает
 * EnsureSessionCredential, но как бэкстоп оно было сломано.
 */
final class TokenManagementDeniedException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function errorCode(): ErrorCode
    {
        return ErrorCode::FORBIDDEN;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return ['reason' => 'interactive_session_required'];
    }
}
