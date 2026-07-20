<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Extensions\SdkBridge\Contracts\ExtensionRegistryState;

final class DatabaseExtensionRegistryState implements ExtensionRegistryState
{
    public function isEnabled(string $extensionId): bool
    {
        try {
            return (bool) DB::table('plugins_registry')
                ->where('plugin_id', $extensionId)
                ->value('is_enabled');
        } catch (\Throwable) {
            // Таблицы реестра может не быть до выполнения миграций ядра.
            return false;
        }
    }
}
