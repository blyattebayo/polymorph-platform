<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Domain\Extensions\SdkBridge\Contracts\ExtensionRegistryState;
use Polymorph\Platform\Domain\Extensions\Trusted\TrustedExtensionSource;

/**
 * Enabled-state gate that reports trusted (withPlugins) plugins as always enabled — they are
 * baked into the deployment as Composer packages and have no plugins_registry row / admin toggle
 * (ADR 0006 Стадия 4). Any other extension falls through to the database-backed state. Because
 * this is the single runtime gate consumed by ExtensionProvider::boot() and every SDK
 * ExtensionState check, decorating it here makes trusted plugins boot their listeners/schedule
 * and pass all is_enabled guards without a DB row.
 */
final class TrustedOrDatabaseExtensionRegistryState implements ExtensionRegistryState
{
    public function __construct(
        private readonly TrustedExtensionSource $trusted,
        private readonly DatabaseExtensionRegistryState $database,
    ) {}

    public function isEnabled(string $extensionId): bool
    {
        return $this->trusted->isTrusted($extensionId) || $this->database->isEnabled($extensionId);
    }
}
