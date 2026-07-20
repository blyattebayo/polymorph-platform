<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

use Polymorph\Platform\Domain\Auth\Core\Models\PersonalAccessToken;

final readonly class PersonalAccessTokenView
{
    public function __construct(
        public int $id,
        public string $name,
        public string $tokenPrefix,
        public ?string $expiresAt,
        public ?string $revokedAt,
        public ?string $lastUsedAt,
        public ?string $createdAt,
        public ?int $userId = null,
        public ?array $user = null,
    ) {}

    public static function fromModel(PersonalAccessToken $token, ?array $user = null): self
    {
        return new self(
            id: (int) $token->id,
            name: (string) $token->name,
            tokenPrefix: (string) $token->token_prefix,
            expiresAt: $token->expires_at?->toIso8601String(),
            revokedAt: $token->revoked_at?->toIso8601String(),
            lastUsedAt: $token->last_used_at?->toIso8601String(),
            createdAt: $token->created_at?->toIso8601String(),
            userId: (int) $token->user_id,
            user: $user,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'token_prefix' => $this->tokenPrefix,
            'expires_at' => $this->expiresAt,
            'revoked_at' => $this->revokedAt,
            'last_used_at' => $this->lastUsedAt,
            'created_at' => $this->createdAt,
        ];

        if ($this->userId !== null) {
            $payload['user_id'] = $this->userId;
        }

        if ($this->user !== null) {
            $payload['user'] = $this->user;
        }

        return $payload;
    }
}
