<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Polymorph\Platform\Domain\Extensions\Services\ExtensionManager;
use Illuminate\Console\Command;

final class PluginsEnableCommand extends Command
{
    protected $signature = 'plugins:enable {pluginId : Plugin identifier}';
    protected $description = 'Enable plugin with route/acl/migration sync.';

    public function handle(ExtensionManager $pluginManager): int
    {
        $pluginId = (string) $this->argument('pluginId');
        $pluginManager->discoverAndSync();
        $entry = $pluginManager->enable($pluginId);
        $this->info("Plugin '{$entry->plugin_id}' enabled.");

        return self::SUCCESS;
    }
}
