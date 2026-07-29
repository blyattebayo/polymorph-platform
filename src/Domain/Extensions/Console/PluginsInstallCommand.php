<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\Extensions\Artifacts\ExtensionArtifactInstaller;
use Polymorph\Platform\Domain\Extensions\Artifacts\LocalZipSource;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException;
use Polymorph\Platform\Domain\Extensions\Core\Models\ExtensionRegistry;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionAutoloadService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionManager;

final class PluginsInstallCommand extends Command
{
    protected $signature = 'plugins:install {source : Plugin id (already unpacked) OR path to a .zip artifact}';

    protected $description = 'Install a plugin from a .zip drop-in artifact (or by id from the plugins root): unpack + register + migrate + enable/upgrade.';

    private ExtensionAutoloadService $autoload;

    public function handle(
        ExtensionManager $pluginManager,
        ExtensionArtifactInstaller $installer,
        ExtensionAutoloadService $autoload,
    ): int {
        $this->autoload = $autoload;

        $source = (string) $this->argument('source');

        if ($this->looksLikeArtifact($source)) {
            return $this->installFromArtifact($source, $pluginManager, $installer);
        }

        return $this->installById($source, $pluginManager);
    }

    private function installFromArtifact(string $zipPath, ExtensionManager $pluginManager, ExtensionArtifactInstaller $installer): int
    {
        try {
            $pluginId = $installer->install(new LocalZipSource($zipPath));
        } catch (ExtensionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line("Unpacked artifact into plugins root: {$pluginId}.");

        // Расширение появилось на диске уже после бутстрапа, поэтому его
        // автолоадер сейчас не зарегистрирован — а включение ниже исполняет
        // его файл маршрутов. Без этого ПЕРВАЯ установка падала с
        // «Class not found»; на апгрейде дефект маскировался тем, что каталог
        // расширения уже лежал на месте при старте процесса.
        $this->autoload->registerExtension($pluginId);

        $before = ExtensionRegistry::query()->where('plugin_id', $pluginId)->first();
        $pluginManager->discoverAndSync();

        if (! $before instanceof ExtensionRegistry) {
            $entry = $pluginManager->enable($pluginId);
            $this->info("Plugin '{$entry->plugin_id}' installed and enabled.");

            return self::SUCCESS;
        }

        if ((bool) $before->is_enabled) {
            $entry = $pluginManager->update($pluginId);
            $this->info("Plugin '{$entry->plugin_id}' upgraded to version {$entry->version}.");

            return self::SUCCESS;
        }

        $this->info("Plugin '{$pluginId}' artifact updated; plugin remains disabled (enable with plugins:enable).");

        return self::SUCCESS;
    }

    private function installById(string $pluginId, ExtensionManager $pluginManager): int
    {
        $pluginManager->discoverAndSync();

        $existing = ExtensionRegistry::query()->where('plugin_id', $pluginId)->first();
        if (! $existing instanceof ExtensionRegistry) {
            $this->error("Plugin '{$pluginId}' not found in plugins root. Unpack it there first (or pass a .zip artifact path).");

            return self::FAILURE;
        }

        if ((bool) $existing->is_enabled) {
            $this->info("Plugin '{$pluginId}' is already installed and enabled.");

            return self::SUCCESS;
        }

        $entry = $pluginManager->enable($pluginId);
        $this->info("Plugin '{$entry->plugin_id}' installed and enabled.");

        return self::SUCCESS;
    }

    private function looksLikeArtifact(string $source): bool
    {
        return strtolower((string) pathinfo($source, PATHINFO_EXTENSION)) === 'zip';
    }
}
