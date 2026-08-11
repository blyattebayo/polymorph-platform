<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Contracts;

use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenId;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;

interface IdGenerator
{
    public function sessionId(): SessionId;

    public function personalAccessTokenId(): PersonalAccessTokenId;
}
