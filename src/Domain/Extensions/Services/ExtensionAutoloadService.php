<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

final class ExtensionAutoloadService
{
    private bool $registered = false;

    public function registerAutoload(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registerPluginComposerAutoloads();

        spl_autoload_register(function (string $class): void {
            if (! str_starts_with($class, 'Plugins\\')) {
                return;
            }

            $path = $this->resolvePath($class);
            if ($path !== null && is_file($path)) {
                require_once $path;
            }
        });

        $this->registered = true;
    }

    private function registerPluginComposerAutoloads(): void
    {
        $root = rtrim((string) config('plugins.root_path'), '/\\');
        if ($root === '' || ! is_dir($root)) {
            return;
        }

        foreach (glob($root.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $pluginBase) {
            $autoloadPath = $pluginBase.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
            if (is_file($autoloadPath)) {
                require_once $autoloadPath;
            }
        }
    }

    private function resolvePath(string $class): ?string
    {
        $parts = explode('\\', $class);
        if (count($parts) < 3 || $parts[0] !== 'Plugins') {
            return null;
        }

        $pluginNamespace = $parts[1];
        $relativeClass = implode(DIRECTORY_SEPARATOR, array_slice($parts, 2)).'.php';
        $pluginId = $this->toSnakeCase($pluginNamespace);
        $root = rtrim((string) config('plugins.root_path'), '/\\');

        if ($root === '') {
            return null;
        }

        $pluginBase = $root.DIRECTORY_SEPARATOR.$pluginId;

        return $pluginBase.DIRECTORY_SEPARATOR.'be'.DIRECTORY_SEPARATOR.$relativeClass;
    }

    private function toSnakeCase(string $value): string
    {
        $normalized = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);
        if (! is_string($normalized)) {
            return strtolower($value);
        }

        return strtolower($normalized);
    }
}
