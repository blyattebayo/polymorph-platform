<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Polymorph\Platform\Domain\Extensions\Services\ExtensionManager;
use Illuminate\Console\Command;

final class PluginsUpdateCommand extends Command
{
    protected $signature = 'plugins:update {pluginId : Plugin identifier}';
    protected $description = 'Update plugin routes/migrations/capabilities for current version.';

    public function handle(ExtensionManager $pluginManager): int
    {
        $pluginId = (string) $this->argument('pluginId');
        $pluginManager->discoverAndSync();
        $entry = $pluginManager->update($pluginId);
        $this->info("Plugin '{$entry->plugin_id}' updated to '{$entry->version}'.");

        return self::SUCCESS;
    }
}
