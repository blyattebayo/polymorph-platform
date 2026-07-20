<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthenticatedCredential;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\CredentialKind;
use Polymorph\Platform\Domain\Users\Queries\FindUserByIdQuery;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final class PatCredentialAuthenticator
{
    public function __construct(
        private readonly PersonalAccessTokenService $personalAccessTokens,
        private readonly FindUserByIdQuery $findUserById,
        private readonly AppLogger $logger,
    ) {}

    public function looksLikePat(string $token): bool
    {
        return $this->personalAccessTokens->looksLikePat($token);
    }

    public function authenticate(string $token): ?AuthenticatedCredential
    {
        if (! $this->looksLikePat($token)) {
            return null;
        }

        if (! (bool) config('pat.enabled', true)) {
            $this->logAuthDenied('disabled');

            return null;
        }

        $pat = $this->personalAccessTokens->authenticate($token);
        if ($pat === null) {
            return null;
        }

        $user = $this->findUserById->execute((int) $pat->user_id);
        if ($user === null) {
            $this->logAuthDenied('inactive_user', (int) $pat->id);

            return null;
        }

        return new AuthenticatedCredential($user, CredentialKind::PersonalAccessToken, $pat);
    }

    private function logAuthDenied(string $reason, ?int $tokenId = null): void
    {
        $this->logger->event('auth.personal_access_token.denied', array_filter([
            'reason' => $reason,
            'token_id' => $tokenId,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
