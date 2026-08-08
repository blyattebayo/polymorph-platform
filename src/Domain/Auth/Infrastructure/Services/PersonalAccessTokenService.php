<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Polymorph\Platform\Domain\Auth\Core\Contracts\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Core\Models\PersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\PatConfig;

final class PersonalAccessTokenService
{
    public function __construct(
        private readonly PersonalAccessTokenRepository $repository,
        private readonly PersonalAccessTokenDenialLogger $denials,
        private readonly PatConfig $config,
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

        $plaintext = $this->config->prefix.Str::random(40);

        return DB::transaction(function () use ($userId, $name, $createdByUserId, $expiresAt, $plaintext): array {
            $token = $this->repository->create(
                userId: $userId,
                name: $name,
                createdByUserId: $createdByUserId,
                tokenHash: $this->hash($plaintext),
                tokenPrefix: $this->visiblePrefix($plaintext),
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
            $this->denials->denied('not_found');

            return null;
        }

        if ($token->isRevoked()) {
            $this->denials->denied('revoked', (int) $token->id);

            return null;
        }

        if ($token->isExpired()) {
            $this->denials->denied('expired', (int) $token->id);

            return null;
        }

        $this->repository->touchLastUsedThrottled((int) $token->id);

        return $token;
    }

    /**
     * Единственное определение «похоже на PAT» в системе: его спрашивает и
     * реестр способов аутентификации (через PatCredentialAuthenticator::supports),
     * и этот сервис как собственную страховку для прямых вызовов.
     */
    public function looksLikePat(string $plaintext): bool
    {
        return str_starts_with($plaintext, $this->config->prefix);
    }

    public function resolveExpiresAt(?string $ttl = null): ?DateTimeImmutable
    {
        $resolvedTtl = $ttl ?? $this->config->defaultTtl;
        if ($resolvedTtl === null || trim($resolvedTtl) === '') {
            return null;
        }

        return (new DateTimeImmutable)->add(new DateInterval($resolvedTtl));
    }

    /**
     * @return list<string>
     */
    public function ttlOptions(): array
    {
        return $this->config->ttlOptions;
    }

    private function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /** Видимый в списках огрызок токена. */
    private function visiblePrefix(string $plaintext): string
    {
        return substr($plaintext, 0, min($this->config->visiblePrefixLength(), strlen($plaintext)));
    }
}
