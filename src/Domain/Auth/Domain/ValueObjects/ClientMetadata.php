<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\ValueObjects;

final readonly class ClientMetadata
{
    public function __construct(
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}
}
