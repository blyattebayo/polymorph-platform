<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionManager;

final class PluginsUninstallCommand extends Command
{
    protected $signature = 'plugins:uninstall {pluginId : Plugin identifier} {--purge : Also drop the plugin tables and data}';

    protected $description = 'Uninstall a plugin (remove from registry; keeps data unless --purge).';

    public function handle(ExtensionManager $pluginManager): int
    {
        $pluginId = (string) $this->argument('pluginId');
        $purge = (bool) $this->option('purge');

        if ($purge && ! $this->confirmPurge($pluginId)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $pluginManager->uninstall($pluginId, $purge);

        $this->info($purge
            ? "Plugin '{$pluginId}' uninstalled and its data purged."
            : "Plugin '{$pluginId}' uninstalled (data preserved).");

        return self::SUCCESS;
    }

    private function confirmPurge(string $pluginId): bool
    {
        if (app()->runningUnitTests() || ! $this->input->isInteractive()) {
            return true;
        }

        return $this->confirm("Drop ALL tables and data of plugin '{$pluginId}'? This cannot be undone.", false);
    }
}
