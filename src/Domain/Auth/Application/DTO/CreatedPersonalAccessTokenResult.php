<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

use Polymorph\Platform\Domain\Auth\Core\Models\PersonalAccessToken;

final readonly class CreatedPersonalAccessTokenResult
{
    public function __construct(
        public PersonalAccessToken $token,
        public string $plaintext,
    ) {}
}
