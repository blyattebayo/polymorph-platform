<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Access;

use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class ExtensionsCapabilities
{
    public const RESOURCE = 'plugins';

    public static function requireManage(): string
    {
        return CapabilityCatalog::requirement(
            self::RESOURCE,
            CapabilityCatalog::ACTION_MANAGE,
        );
    }
}
