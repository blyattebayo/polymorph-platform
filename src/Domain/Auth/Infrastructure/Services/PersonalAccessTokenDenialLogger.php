<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Единственный автор записи `auth.personal_access_token.denied`.
 *
 * До этого приватный метод с одинаковым телом жил и в PersonalAccessTokenService,
 * и в PatCredentialAuthenticator: одна запись лога — два определения, а полный
 * список причин отказа приходилось собирать чтением обоих файлов.
 *
 * Причины: `disabled` (приём PAT выключен настройкой), `not_found` (нет токена с
 * таким хешом), `revoked`, `expired`, `inactive_user` (владелец заблокирован).
 */
final class PersonalAccessTokenDenialLogger
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    public function denied(string $reason, ?int $tokenId = null): void
    {
        $this->logger->event('auth.personal_access_token.denied', array_filter([
            'reason' => $reason,
            'token_id' => $tokenId,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
