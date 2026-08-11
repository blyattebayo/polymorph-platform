<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\OAuth\Data;

final readonly class OAuthClient
{
    /** @param list<string> $redirectUris */
    public function __construct(
        public string $id,
        public string $name,
        public array $redirectUris,
    ) {}

    public function acceptsRedirect(string $uri): bool
    {
        return in_array($uri, $this->redirectUris, true);
    }
}
