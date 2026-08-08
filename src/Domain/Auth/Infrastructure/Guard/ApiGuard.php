<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Guard;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use InvalidArgumentException;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;

/**
 * Адаптер контракта Laravel к {@see AuthenticationContext}. Собственного
 * состояния у гарда нет: и «кто», и «чем доказано» держит контекст.
 *
 * Отсюда ушли поля $user/$credential/$resolved/$manualUser, ручная
 * синхронизация атрибутов запроса и setRequest() вместе с
 * `$app->refresh('request', ...)`: контекст сам смотрит на текущий запрос,
 * поэтому подмена объекта Request больше не требует ничего переносить руками.
 */
final class ApiGuard implements Guard
{
    public function __construct(
        private readonly AuthenticationContext $context,
    ) {}

    public function check(): bool
    {
        return $this->user() instanceof Authenticatable;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        return $this->context->user();
    }

    public function id(): int|string|null
    {
        return $this->context->authIdentifier();
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->context->hasCredential();
    }

    public function setUser(Authenticatable $user): static
    {
        if (! $user instanceof User) {
            throw new InvalidArgumentException(
                'ApiGuard authenticates '.User::class.' only, got '.$user::class.'.',
            );
        }

        $this->context->assume($user);

        return $this;
    }
}
