<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Access;

final class CapabilityCatalog
{
    public const ACTION_ACCESS = 'access';

    public const ACTION_READ = 'read';

    public const ACTION_WRITE = 'write';

    public const ACTION_DELETE = 'delete';

    public const ACTION_WILDCARD = '*';

    public const EFFECT_ALLOW = 'allow';

    /**
     * Core platform policy actions. Domains may add more via ActionDefinitionProvider
     * (tag: access.action_providers).
     *
     * @return list<string>
     */
    public static function policyActions(): array
    {
        return [
            self::ACTION_ACCESS,
            self::ACTION_READ,
            self::ACTION_WRITE,
            self::ACTION_DELETE,
            self::ACTION_WILDCARD,
        ];
    }

    public static function capabilityKey(string $resource, string $action = self::ACTION_ACCESS): string
    {
        return $resource.'/'.$action;
    }
}
