<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\OAuth;

use DateTimeImmutable;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\AuthorizationCode;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\OAuthClient;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\OAuthGrant;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\TokenHash;

interface OAuthStore
{
    /** @param list<string> $redirectUris */
    public function registerClient(string $id, string $name, array $redirectUris, DateTimeImmutable $createdAt): void;

    public function client(string $id): ?OAuthClient;

    public function saveAuthorizationCode(
        TokenHash $hash,
        AuthorizationCode $code,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): void;

    public function consumeAuthorizationCode(TokenHash $hash, DateTimeImmutable $now): ?AuthorizationCode;

    public function createGrant(
        OAuthGrant $grant,
        TokenHash $refreshHash,
        DateTimeImmutable $refreshExpiresAt,
        TokenHash $accessHash,
        DateTimeImmutable $accessExpiresAt,
        DateTimeImmutable $now,
    ): void;

    public function rotateRefreshToken(
        TokenHash $currentRefreshHash,
        string $clientId,
        string $resource,
        TokenHash $nextRefreshHash,
        DateTimeImmutable $nextRefreshExpiresAt,
        TokenHash $accessHash,
        DateTimeImmutable $accessExpiresAt,
        DateTimeImmutable $now,
    ): ?OAuthGrant;

    public function grantForAccessToken(TokenHash $hash, string $resource, DateTimeImmutable $now): ?OAuthGrant;

    public function revoke(TokenHash $hash, string $clientId): void;

    public function prune(DateTimeImmutable $now): int;
}
