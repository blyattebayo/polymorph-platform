<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Identity;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * Единственный источник правды о том, кто аутентифицирован в текущем запросе.
 *
 * Заменил пару CurrentActorResolver + AuthenticatedCredentialResolver. Разница
 * не в количестве классов, а в количестве источников: раньше «кто я» собиралось
 * из атрибута запроса, `$request->user()` и `Auth::user()`, а «чем доказано» —
 * из атрибута либо синтезировалось на месте. Теперь ответ один и один тип:
 * {@see AuthenticatedCredential}.
 *
 * Состояние:
 * - разобранный из запроса credential кэшируется вместе со ссылкой на сам
 *   Request, поэтому подмена объекта запроса (`$app->refresh('request', ...)`)
 *   автоматически заставляет разобрать заново, а токен не верифицируется
 *   повторно на каждый вызов;
 * - `assume()` (ручной путь `Auth::setUser()`/`actingAs`) живёт ОТДЕЛЬНО от
 *   запроса: его задают до того, как запрос создан, и он обязан пережить смену
 *   объекта Request.
 *
 * Биндинг scoped, а не singleton: под Octane `assumed` не должен протекать
 * между запросами воркера.
 */
final class AuthenticationContext
{
    private ?AuthenticatedCredential $assumed = null;

    private ?AuthenticatedCredential $resolved = null;

    private ?Request $resolvedFor = null;

    public function __construct(
        private readonly RequestCredentialResolver $credentials,
    ) {}

    public function credential(): ?AuthenticatedCredential
    {
        $request = $this->request();

        if (! $request instanceof Request) {
            return $this->assumed;
        }

        if ($this->resolvedFor !== $request) {
            $this->resolvedFor = $request;
            $this->resolved = $this->credentials->authenticate($request);
        }

        // Разобранный из запроса credential сильнее назначенного кодом: он несёт
        // вид и sessionId, которых assume() знать не может. Ручной путь работает
        // там, где транспортного доказательства нет вовсе.
        return $this->resolved ?? $this->assumed;
    }

    public function actor(): ?UserIdentity
    {
        return $this->credential()?->user;
    }

    public function requireActor(): UserIdentity
    {
        $actor = $this->actor();

        abort_unless($actor instanceof UserIdentity, 401);

        return $actor;
    }

    public function user(): ?User
    {
        return $this->credential()?->user;
    }

    public function requireUser(): User
    {
        $user = $this->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function authIdentifier(): int|string|null
    {
        $identifier = $this->user()?->getAuthIdentifier();

        if ($identifier === null || is_int($identifier) || is_string($identifier)) {
            return $identifier;
        }

        return (string) $identifier;
    }

    /**
     * Уже есть ответ, и получен он без разбора токена. Нужен гарду для
     * `hasUser()`, который по контракту Laravel не должен ничего резолвить.
     */
    public function hasCredential(): bool
    {
        return ($this->resolvedFor === $this->request() && $this->resolved instanceof AuthenticatedCredential)
            || $this->assumed instanceof AuthenticatedCredential;
    }

    /**
     * Назначить актора напрямую, без транспортного доказательства
     * (`Auth::setUser()`, `actingAs` в тестах, имперсонация из SDK).
     */
    public function assume(User $user): void
    {
        $this->assumed = AuthenticatedCredential::assumed($user);
    }

    private function request(): ?Request
    {
        if (! App::bound('request')) {
            return null;
        }

        $request = App::make('request');

        return $request instanceof Request ? $request : null;
    }
}
