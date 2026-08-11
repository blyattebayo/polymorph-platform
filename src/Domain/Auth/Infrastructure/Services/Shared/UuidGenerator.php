<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared;

use Illuminate\Support\Str;
use Polymorph\Platform\Domain\Auth\Application\Contracts\IdGenerator;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenId;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;

final class UuidGenerator implements IdGenerator
{
    public function sessionId(): SessionId
    {
        return new SessionId((string) Str::uuid());
    }

    public function personalAccessTokenId(): PersonalAccessTokenId
    {
        return new PersonalAccessTokenId((string) Str::uuid());
    }
}
