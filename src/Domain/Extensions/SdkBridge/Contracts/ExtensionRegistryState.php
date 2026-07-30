<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge\Contracts;

interface ExtensionRegistryState
{
    public function isEnabled(string $extensionId): bool;

    /**
     * Есть ли расширение в реестре вообще (включая выключенные): ряд создаётся
     * при discovery/sync, поэтому известность != включённость.
     */
    public function isKnown(string $extensionId): bool;
}
