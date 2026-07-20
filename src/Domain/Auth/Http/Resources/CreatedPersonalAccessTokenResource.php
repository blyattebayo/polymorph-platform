<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Resources;

use Polymorph\Platform\Domain\Auth\Application\DTO\CreatedPersonalAccessTokenResult;

final class CreatedPersonalAccessTokenResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromResult(CreatedPersonalAccessTokenResult $created): array
    {
        $token = $created->token;

        return [
            'access_token' => $created->plaintext,
            'token_type' => 'personal_access',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'token' => [
                'id' => (int) $token->id,
                'name' => (string) $token->name,
                'token_prefix' => (string) $token->token_prefix,
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ],
        ];
    }
}
