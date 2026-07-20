<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Domain\Extensions\SdkBridge\Contracts\ExtensionRegistryState;
use Polymorph\Sdk\Contracts\ExtensionState;

/**
 * Host-адаптер {@see ExtensionState}. Расширения регистрируются в реестре ядра
 * (таблица plugins_registry); проверка enabled-состояния — через контракт
 * {@see ExtensionRegistryState}.
 */
final class SdkExtensionState implements ExtensionState
{
    public function __construct(
        private readonly ExtensionRegistryState $registryState,
    ) {
    }

    public function isEnabled(string $extensionId): bool
    {
        return $this->registryState->isEnabled($extensionId);
    }
}
