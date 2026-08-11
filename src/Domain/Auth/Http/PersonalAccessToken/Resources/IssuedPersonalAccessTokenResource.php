<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Resources;

use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data\IssuedPersonalAccessToken;

final class IssuedPersonalAccessTokenResource
{
    /** @return array<string, mixed> */
    public static function fromResult(IssuedPersonalAccessToken $issued): array
    {
        return [
            'plaintext' => $issued->revealPlaintext(),
            'metadata' => PersonalAccessTokenResource::fromView($issued->token),
        ];
    }
}
