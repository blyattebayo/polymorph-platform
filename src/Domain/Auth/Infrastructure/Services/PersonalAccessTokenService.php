<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Polymorph\Platform\Domain\Auth\Core\Contracts\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Core\Models\PersonalAccessToken;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PersonalAccessTokenService
{
    public function __construct(
        private readonly PersonalAccessTokenRepository $repository,
        private readonly AppLogger $logger,
    ) {}

    /**
     * @return array{token: PersonalAccessToken, plaintext: string}
     */
    public function create(
        int $userId,
        string $name,
        int $createdByUserId,
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Personal access token name is required.');
        }

        $plaintext = $this->generatePlaintext();

        return DB::transaction(function () use ($userId, $name, $createdByUserId, $expiresAt, $plaintext): array {
            $token = $this->repository->create(
                userId: $userId,
                name: $name,
                createdByUserId: $createdByUserId,
                tokenHash: $this->hash($plaintext),
                tokenPrefix: $this->prefix($plaintext),
                expiresAt: $expiresAt,
            );

            return ['token' => $token, 'plaintext' => $plaintext];
        });
    }

    public function authenticate(string $plaintext): ?PersonalAccessToken
    {
        if (! $this->looksLikePat($plaintext)) {
            return null;
        }

        $token = $this->repository->findByHash($this->hash($plaintext));

        if (! $token instanceof PersonalAccessToken) {
            $this->logAuthDenied('not_found');

            return null;
        }

        if ($token->isRevoked()) {
            $this->logAuthDenied('revoked', (int) $token->id);

            return null;
        }

        if ($token->isExpired()) {
            $this->logAuthDenied('expired', (int) $token->id);

            return null;
        }

        $this->repository->touchLastUsedThrottled((int) $token->id);

        return $token;
    }

    public function looksLikePat(string $plaintext): bool
    {
        $prefix = (string) config('pat.prefix', 'pmph_pat_');

        return $prefix !== '' && str_starts_with($plaintext, $prefix);
    }

    public function resolveExpiresAt(?string $ttl = null): ?DateTimeImmutable
    {
        $resolvedTtl = $ttl ?? config('pat.default_ttl');
        if (! is_string($resolvedTtl) || trim($resolvedTtl) === '') {
            return null;
        }

        return (new DateTimeImmutable)->add(new DateInterval($resolvedTtl));
    }

    /**
     * @return list<string>
     */
    public function ttlOptions(): array
    {
        return array_values(array_filter(
            array_map(
                static fn (mixed $ttl): string => trim((string) $ttl),
                (array) config('pat.ttl_options', []),
            ),
            static fn (string $ttl): bool => $ttl !== '',
        ));
    }

    private function generatePlaintext(): string
    {
        $prefix = (string) config('pat.prefix', 'pmph_pat_');

        return $prefix.Str::random(40);
    }

    private function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    private function prefix(string $plaintext): string
    {
        $prefix = (string) config('pat.prefix', 'pmph_pat_');

        return substr($plaintext, 0, min(strlen($prefix) + 6, strlen($plaintext)));
    }

    private function logAuthDenied(string $reason, ?int $tokenId = null): void
    {
        $this->logger->event('auth.personal_access_token.denied', array_filter([
            'reason' => $reason,
            'token_id' => $tokenId,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
