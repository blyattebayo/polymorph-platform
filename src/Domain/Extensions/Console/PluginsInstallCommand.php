<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\Extensions\Artifacts\ExtensionArtifactInstaller;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException;

final class PluginsInstallCommand extends Command
{
    protected $signature = 'plugins:install {artifact : Path to a plugin .zip artifact}';

    protected $description = 'Atomically install or replace one plugin artifact. A process restart activates it.';

    public function handle(ExtensionArtifactInstaller $installer): int
    {
        try {
            $pluginId = $installer->install((string) $this->argument('artifact'));
        } catch (ExtensionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Plugin '{$pluginId}' installed.");
        $this->line('Restart HTTP and worker processes to load this artifact.');

        return self::SUCCESS;
    }
}
