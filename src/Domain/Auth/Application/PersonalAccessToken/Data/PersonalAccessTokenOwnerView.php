<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data;

final readonly class PersonalAccessTokenOwnerView
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}
