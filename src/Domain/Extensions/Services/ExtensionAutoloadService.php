<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Composer\Autoload\ClassLoader;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException;

final class ExtensionAutoloadService
{
    private bool $registered = false;

    public function __construct(
        private readonly ExtensionDiscoveryService $discovery,
    ) {}

    public function registerAutoload(): void
    {
        if ($this->registered) {
            return;
        }

        foreach ($this->discovery->discoverAll() as $extension) {
            $autoloadPath = dirname($extension->manifestPath)
                .DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
            if (! is_file($autoloadPath)) {
                throw new ExtensionException(
                    "Plugin '{$extension->id}' artifact has no vendor/autoload.php.",
                );
            }

            /** @var mixed $loader */
            $loader = require $autoloadPath;
            if (! $loader instanceof ClassLoader) {
                throw new ExtensionException(
                    "Plugin '{$extension->id}' vendor/autoload.php did not return a Composer ClassLoader.",
                );
            }

            // Plugin dependencies must never shadow host contracts.
            $loader->unregister();
            $loader->register(false);
        }

        $this->registered = true;
    }
}
