<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\ValueObjects;

/**
 * Политика сессий: сколько их можно держать одновременно и как долго живёт
 * семья refresh-токенов.
 *
 * Лежала в конфиге jwt.*, хотя к формату токена отношения не имеет, и читалась
 * россыпью из репозитория и middleware — с разными литеральными дефолтами в
 * каждой точке.
 */
final readonly class AuthSessionConfig
{
    public function __construct(
        /** Абсолютный потолок жизни семьи refresh-сессий, секунды. */
        public int $refreshFamilyTtl,
        /** Максимум одновременных активных сессий на пользователя, не меньше 1. */
        public int $maxSessionsPerUser,
    ) {}

    /**
     * @param  array<string, mixed>  $config  секция jwt
     */
    public static function fromArray(array $config): self
    {
        return new self(
            refreshFamilyTtl: (int) ($config['refresh_family_ttl'] ?? 90 * 24 * 60 * 60),
            maxSessionsPerUser: max(1, (int) ($config['max_sessions_per_user'] ?? 20)),
        );
    }
}
