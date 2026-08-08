<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\PresentedToken;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\PresentedTokenExtractor;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Polymorph\Platform\SharedKernel\Identity\RequestCredentialResolver;

/**
 * Разбор учётных данных запроса: достать токен -> найти способ -> проверить,
 * что аккаунт активен. Сам метод НИКОГДА не бросает 401: неудача записывается
 * в атрибуты запроса и возвращается null.
 *
 * 401 собирается дальше по цепочке — Authenticate бросает AuthenticationException,
 * а FrameworkErrorResolver достаёт причину из этих атрибутов. Раньше рядом жил
 * второй, альтернативный путь (флаг $required + respondUnauthorized), который
 * не был подключён ни к одному вызову и только создавал впечатление, что 401 с
 * meta.reason формируется здесь.
 *
 * Результат разбора здесь НЕ публикуется: его держит {@see AuthenticationContext}.
 * В атрибутах запроса остаётся только диагностика отказа — её читает слой
 * ошибок, и к идентичности она отношения не имеет.
 */
final class RequestCredentialAuthenticator implements RequestCredentialResolver
{
    public const FAILURE_REASON_ATTRIBUTE = 'auth.failure_reason';

    public const FAILURE_MESSAGE_ATTRIBUTE = 'auth.failure_message';

    private const MESSAGES = [
        'missing_token' => 'Access token cookie is missing.',
        'invalid_token' => 'Access token is invalid.',
        'inactive_user' => 'User account is not active.',
    ];

    public function __construct(
        private readonly PresentedTokenExtractor $tokens,
        private readonly CredentialAuthenticatorRegistry $credentials,
    ) {}

    public function authenticate(Request $request): ?AuthenticatedCredential
    {
        $token = $this->tokens->fromRequest($request);
        if (! $token instanceof PresentedToken) {
            $this->markFailure($request, 'missing_token');

            return null;
        }

        $credential = $this->credentials->authenticate($token);
        if ($credential === null) {
            $this->markFailure($request, 'invalid_token');

            return null;
        }

        if (! $credential->user->isActiveAccount()) {
            $this->markFailure($request, 'inactive_user');

            return null;
        }

        $request->attributes->remove(self::FAILURE_REASON_ATTRIBUTE);
        $request->attributes->remove(self::FAILURE_MESSAGE_ATTRIBUTE);

        return $credential;
    }

    public static function failureMessage(string $reason): string
    {
        return self::MESSAGES[$reason] ?? 'Unknown authentication failure.';
    }

    private function markFailure(Request $request, string $reason): void
    {
        $request->attributes->set(self::FAILURE_REASON_ATTRIBUTE, $reason);
        $request->attributes->set(self::FAILURE_MESSAGE_ATTRIBUTE, self::failureMessage($reason));
    }
}
