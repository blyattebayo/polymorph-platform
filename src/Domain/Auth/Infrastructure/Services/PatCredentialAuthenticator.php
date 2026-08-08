<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Polymorph\Platform\Domain\Auth\Core\Contracts\CredentialAuthenticator;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\PatConfig;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\PresentedToken;
use Polymorph\Platform\Domain\Users\Queries\FindUserByIdQuery;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final class PatCredentialAuthenticator implements CredentialAuthenticator
{
    public function __construct(
        private readonly PersonalAccessTokenService $personalAccessTokens,
        private readonly FindUserByIdQuery $findUserById,
        private readonly AppLogger $logger,
        private readonly PatConfig $config,
    ) {}

    /**
     * Что такое «похоже на PAT», знает сервис, который эти токены и выпускает.
     * Раньше тот же предикат был публичным ещё и здесь — и диспетчер спрашивал
     * его отдельно, до вызова authenticate().
     */
    public function supports(PresentedToken $token): bool
    {
        return $this->personalAccessTokens->looksLikePat($token->value);
    }

    public function attempt(PresentedToken $token): ?AuthenticatedCredential
    {
        if (! $this->config->enabled) {
            $this->logAuthDenied('disabled');

            return null;
        }

        $pat = $this->personalAccessTokens->authenticate($token->value);
        if ($pat === null) {
            return null;
        }

        $user = $this->findUserById->execute((int) $pat->user_id);
        if ($user === null) {
            $this->logAuthDenied('inactive_user', (int) $pat->id);

            return null;
        }

        return AuthenticatedCredential::personalAccessToken($user);
    }

    private function logAuthDenied(string $reason, ?int $tokenId = null): void
    {
        $this->logger->event('auth.personal_access_token.denied', array_filter([
            'reason' => $reason,
            'token_id' => $tokenId,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
