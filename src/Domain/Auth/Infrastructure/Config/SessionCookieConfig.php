<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Config;

use Polymorph\Platform\Domain\Auth\Application\Exceptions\AuthConfigurationException;

final readonly class SessionCookieConfig
{
    public function __construct(
        public bool $secure,
        public string $sameSite,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $keys = array_keys($config);
        sort($keys);

        if ($keys !== ['samesite', 'secure']) {
            throw AuthConfigurationException::invalid('cookie config must contain exactly secure and samesite');
        }

        $secure = $config['secure'];
        $sameSite = $config['samesite'];

        if (! is_bool($secure)) {
            throw AuthConfigurationException::invalid('cookie secure must be a boolean');
        }

        if (! is_string($sameSite)) {
            throw AuthConfigurationException::invalid('cookie SameSite must be Strict, Lax or None');
        }

        $sameSite = strtolower(trim($sameSite));
        if (! in_array($sameSite, ['strict', 'lax', 'none'], true)) {
            throw AuthConfigurationException::invalid('cookie SameSite must be Strict, Lax or None');
        }

        if ($sameSite === 'none' && ! $secure) {
            throw AuthConfigurationException::invalid('cookie SameSite=None requires secure=true');
        }

        return new self(
            secure: $secure,
            sameSite: $sameSite,
        );
    }
}
