<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Access;

use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Управление жизненным циклом расширений. Ресурс 'plugins' (мн. число)
 * намеренно: 'plugin' был бы префиксом-родителем всех V1-неймспейсов
 * plugin.{id}.* и через точечный матчер раздавал бы их скопом.
 * Было: 'plugin.manage' с action 'access'.
 */
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
