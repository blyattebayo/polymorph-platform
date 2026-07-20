<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge\Contracts;

interface ExtensionRegistryState
{
    public function isEnabled(string $extensionId): bool;
}
