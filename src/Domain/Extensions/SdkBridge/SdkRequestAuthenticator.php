<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Polymorph\Sdk\Identity\Actor;
use Polymorph\Sdk\Identity\RequestAuthenticator;

/**
 * Host-адаптер {@see RequestAuthenticator}. Расширение может действовать только
 * от имени УЖЕ аутентифицированного пользователя текущего запроса (кросс-юзер
 * имперсонация намеренно не поддерживается — fail-open footgun V1 удалён).
 */
final class SdkRequestAuthenticator implements RequestAuthenticator
{
    public const EXTENSION_ID_ATTRIBUTE = 'extension.id';

    public function __construct(private readonly AppLogger $logger) {}

    public function authenticateAs(int $userId): ?Actor
    {
        $request = request();
        if (! $request instanceof Request) {
            return null;
        }

        if ((int) Auth::id() !== $userId) {
            $this->log($request)->warning('extension.impersonation_denied', [
                'extension_id' => $request->attributes->get(self::EXTENSION_ID_ATTRIBUTE),
                'target_user_id' => $userId,
                'current_user_id' => Auth::id(),
            ]);

            return null;
        }

        $user = User::query()->find($userId);
        if (! $user instanceof User) {
            return null;
        }

        // Единственная публикация актора — через гард: он кладёт его в
        // AuthenticationContext, откуда его видит весь остальной код. Отдельного
        // атрибута запроса, который надо было держать в синхроне, больше нет.
        Auth::setUser($user);

        $this->log($request)->info('extension.impersonation_granted', [
            'extension_id' => $request->attributes->get(self::EXTENSION_ID_ATTRIBUTE),
            'target_user_id' => $userId,
        ]);

        return ActorMapper::fromUser($user);
    }

    private function log(Request $request): AppLogger
    {
        $extensionId = $request->attributes->get(self::EXTENSION_ID_ATTRIBUTE);

        return is_string($extensionId)
            ? $this->logger->withContext(['extension_id' => $extensionId])
            : $this->logger;
    }
}
