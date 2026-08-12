<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException as PluginException;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;
use Polymorph\Platform\Domain\Extensions\Manifest\ManifestV2Validator;

final class ExtensionDiscoveryService
{
    private const MANIFEST = 'extension.json';

    public function __construct(
        private readonly ExtensionAclManifestParser $aclManifestParser,
        private readonly ManifestV2Validator $manifestValidator,
    ) {}

    /** @return list<DiscoveredExtension> */
    public function discoverAll(): array
    {
        $extensions = [];
        $rootPath = (string) config('plugins.root_path');
        if ($rootPath === '' || ! is_dir($rootPath)) {
            return [];
        }

        $directories = glob($rootPath.'/*', GLOB_ONLYDIR) ?: [];
        sort($directories);
        foreach ($directories as $directory) {
            $extension = $this->loadFromDirectory($directory);

            if ($extension !== null) {
                $extensions[] = $extension;
            }
        }

        return $extensions;
    }

    public function find(string $id): ?DiscoveredExtension
    {
        foreach ($this->discoverAll() as $extension) {
            if ($extension->id === $id) {
                return $extension;
            }
        }

        return null;
    }

    private function loadFromDirectory(string $directory): ?DiscoveredExtension
    {
        $manifestPath = $directory.DIRECTORY_SEPARATOR.self::MANIFEST;

        return is_file($manifestPath) ? $this->loadExtension($manifestPath, $directory) : null;
    }

    private function loadExtension(string $manifestPath, string $directory): DiscoveredExtension
    {
        $raw = file_get_contents($manifestPath);
        if (! is_string($raw) || trim($raw) === '') {
            throw new PluginException("Manifest {$manifestPath}: file is empty.");
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new PluginException("Manifest {$manifestPath}: invalid JSON.");
        }

        try {
            $manifest = $this->manifestValidator->validate($decoded, $manifestPath);
        } catch (\InvalidArgumentException $exception) {
            throw new PluginException($exception->getMessage(), previous: $exception);
        }

        if ($manifest->id !== basename($directory)) {
            throw new PluginException(
                "Manifest {$manifestPath}: id '{$manifest->id}' must equal directory name '".basename($directory)."'.",
            );
        }

        $contributes = $manifest->contributes;
        $routeFile = $directory.DIRECTORY_SEPARATOR.'be/routes.php';
        $frontendBundle = $directory.DIRECTORY_SEPARATOR.'fe'.DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'plugin.js';
        return new DiscoveredExtension(
            id: $manifest->id,
            name: $manifest->name,
            version: $manifest->version,
            sdkVersion: $manifest->sdk,
            manifestPath: $manifestPath,
            providerClass: $manifest->provider,
            backendRouteFile: is_file($routeFile) ? $routeFile : null,
            capabilityDefinitions: $this->aclManifestParser->parseCapabilities($contributes, $manifest->id),
            defaultAdminRoles: $this->aclManifestParser->parseDefaultAdminRoles($contributes),
            hasFrontend: is_file($frontendBundle),
            pluginRoles: $this->aclManifestParser->parseRoles($contributes, $manifest->id),
        );
    }
}
