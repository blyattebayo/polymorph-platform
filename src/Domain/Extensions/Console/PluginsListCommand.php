<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;

final class PluginsListCommand extends Command
{
    protected $signature = 'plugins:list';

    protected $description = 'List installed plugins. Every listed directory is active.';

    public function handle(ExtensionDiscoveryService $discovery): int
    {
        $rows = array_map(static fn ($extension): array => [
            $extension->id,
            $extension->version,
            $extension->sdkVersion,
            $extension->hasFrontend ? 'yes' : 'no',
            $extension->backendRouteFile !== null ? 'yes' : 'no',
        ], $discovery->discoverAll());

        $this->table(['Plugin', 'Version', 'SDK', 'Frontend', 'Routes'], $rows);

        return self::SUCCESS;
    }
}
