<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Resources;

use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data\PersonalAccessTokenView;
use Polymorph\Platform\Support\DateTime\Iso8601Formatter;

final class PersonalAccessTokenResource
{
    /** @return array<string, mixed> */
    public static function fromView(PersonalAccessTokenView $token): array
    {
        $payload = [
            'id' => $token->id,
            'name' => $token->name,
            'display_hint' => $token->displayHint,
            'scopes' => $token->scopes,
            'status' => $token->status->value,
            'issued_at' => Iso8601Formatter::format($token->issuedAt),
            'expires_at' => Iso8601Formatter::format($token->expiresAt),
            'last_used_at' => Iso8601Formatter::format($token->lastUsedAt),
        ];

        if ($token->owner !== null) {
            $payload['user'] = [
                'id' => $token->owner->id,
                'name' => $token->owner->name,
                'email' => $token->owner->email,
            ];
        }

        return $payload;
    }

    /**
     * @param  list<PersonalAccessTokenView>  $tokens
     * @return list<array<string, mixed>>
     */
    public static function collection(array $tokens): array
    {
        return array_map(self::fromView(...), $tokens);
    }
}
