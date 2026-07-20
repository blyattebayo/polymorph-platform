<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Polymorph\Platform\Domain\Extensions\Services\ExtensionManager;
use Illuminate\Console\Command;

final class PluginsDisableCommand extends Command
{
    protected $signature = 'plugins:disable {pluginId : Plugin identifier} {--force : Disable even if enabled plugins depend on it}';
    protected $description = 'Disable plugin and detach plugin routes.';

    public function handle(ExtensionManager $pluginManager): int
    {
        $pluginId = (string) $this->argument('pluginId');
        $entry = $pluginManager->disable($pluginId, (bool) $this->option('force'));
        $this->info("Plugin '{$entry->plugin_id}' disabled.");

        return self::SUCCESS;
    }
}
