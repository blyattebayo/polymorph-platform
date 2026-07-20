<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionManager;

final class PluginsListCommand extends Command
{
    protected $signature = 'plugins:list {--sync : Sync manifests before listing}';

    protected $description = 'List plugins and their lifecycle status.';

    public function handle(ExtensionManager $pluginManager): int
    {
        if ((bool) $this->option('sync')) {
            $pluginManager->discoverAndSync();
        }

        $rows = $pluginManager->listRegistry()->map(static function ($plugin): array {
            return [
                $plugin->plugin_id,
                $plugin->version,
                $plugin->is_enabled ? 'enabled' : 'disabled',
                $plugin->last_warning ?? '',
            ];
        })->all();

        $this->table(['Plugin', 'Version', 'Status', 'Warning'], $rows);

        return self::SUCCESS;
    }
}
