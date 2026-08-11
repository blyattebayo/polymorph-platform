<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException as PluginException;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\ExtensionDiscoveryFailure;
use Polymorph\Platform\Domain\Extensions\Manifest\ManifestV2Validator;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Throwable;

final class ExtensionDiscoveryService
{
    private const MANIFEST = 'extension.json';

    /** @var list<ExtensionDiscoveryFailure> */
    private array $failures = [];

    public function __construct(
        private readonly ExtensionAclManifestParser $aclManifestParser,
        private readonly ManifestV2Validator $manifestValidator,
        private readonly AppLogger $logger,
    ) {}

    /** @return list<DiscoveredExtension> */
    public function discoverAll(): array
    {
        $this->failures = [];
        $extensions = [];
        $rootPath = (string) config('plugins.root_path');
        if ($rootPath === '' || ! is_dir($rootPath)) {
            return [];
        }

        $directories = glob($rootPath.'/*', GLOB_ONLYDIR) ?: [];
        sort($directories);
        foreach ($directories as $directory) {
            if (str_starts_with(basename($directory), '_')) {
                continue;
            }

            try {
                $extension = $this->loadFromDirectory($directory);
            } catch (Throwable $exception) {
                $this->failures[] = new ExtensionDiscoveryFailure($directory, $exception->getMessage());
                $this->logger->error('extensions.discovery_failed', [
                    'path' => $directory,
                    'exception' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($extension instanceof DiscoveredExtension) {
                $extensions[] = $extension;
            }
        }

        return $this->sortByDependencies($extensions);
    }

    /** @return list<ExtensionDiscoveryFailure> */
    public function failures(): array
    {
        return $this->failures;
    }

    private function loadFromDirectory(string $directory): ?DiscoveredExtension
    {
        $manifestPath = $directory.DIRECTORY_SEPARATOR.self::MANIFEST;

        return is_file($manifestPath) ? $this->loadExtension($manifestPath, $directory) : null;
    }

    /**
     * @param  list<DiscoveredExtension>  $extensions
     * @return list<DiscoveredExtension>
     */
    private function sortByDependencies(array $extensions): array
    {
        $byId = [];
        foreach ($extensions as $extension) {
            $byId[$extension->id] = $extension;
        }

        $sorted = [];
        $visited = [];
        $inProgress = [];
        $visit = function (string $extensionId) use (&$visit, &$sorted, &$visited, &$inProgress, $byId): void {
            if (isset($visited[$extensionId])) {
                return;
            }
            if (isset($inProgress[$extensionId])) {
                throw new PluginException("Cyclic extension dependency detected involving '{$extensionId}'.");
            }
            $inProgress[$extensionId] = true;
            foreach (array_keys($byId[$extensionId]->dependencies) as $dependencyId) {
                if (isset($byId[$dependencyId])) {
                    $visit($dependencyId);
                }
            }
            unset($inProgress[$extensionId]);
            $visited[$extensionId] = true;
            $sorted[] = $byId[$extensionId];
        };

        foreach (array_keys($byId) as $extensionId) {
            $visit($extensionId);
        }

        return $sorted;
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

        $contributes = $manifest->contributes;
        $frontend = is_array($contributes['frontend'] ?? null) ? $contributes['frontend'] : [];
        $navigation = is_array($contributes['navigation'] ?? null) ? $contributes['navigation'] : [];
        $navSection = is_string($navigation['section'] ?? null) ? trim($navigation['section']) : null;
        if ($navSection !== null && $navSection !== '' && ! in_array($navSection, ['content', 'system'], true)) {
            throw new PluginException(
                "Manifest {$manifestPath}: contributes.navigation.section '{$navSection}' is invalid; expected 'content' or 'system'.",
            );
        }
        $navSection = $navSection === '' ? null : $navSection;
        $this->validateFrontendUiMode($frontend['ui']['mode'] ?? null, $manifestPath);

        $routeFile = $directory.DIRECTORY_SEPARATOR.'be/routes.php';
        $coreVersionRange = is_string($decoded['coreVersionRange'] ?? null) ? trim($decoded['coreVersionRange']) : '*';
        $tablePrefix = is_string(data_get($decoded, 'db.tablePrefix'))
            ? trim((string) data_get($decoded, 'db.tablePrefix'))
            : '';

        return new DiscoveredExtension(
            id: $manifest->id,
            name: $manifest->name,
            version: $manifest->version,
            coreVersionRange: $coreVersionRange !== '' ? $coreVersionRange : '*',
            tablePrefix: $tablePrefix,
            manifestPath: $manifestPath,
            manifestHash: hash('sha256', $raw),
            providerClass: $manifest->provider,
            backendRouteFile: is_file($routeFile) ? $routeFile : null,
            capabilityDefinitions: $this->aclManifestParser->parseCapabilities($contributes, $manifest->id),
            defaultAdminRoles: $this->aclManifestParser->parseDefaultAdminRoles($contributes),
            frontendBundle: is_string($frontend['bundle'] ?? null) ? $frontend['bundle'] : null,
            frontendMountPath: is_string($frontend['mountPath'] ?? null) ? $frontend['mountPath'] : null,
            frontendNavTitle: is_string($navigation['title'] ?? null) ? $navigation['title'] : null,
            frontendNavSection: $navSection,
            dependencies: $this->parseDependencies($decoded),
            pluginRoles: $this->aclManifestParser->parseRoles($contributes, $manifest->id),
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, string>
     */
    private function parseDependencies(array $manifest): array
    {
        $dependencies = [];
        foreach (($manifest['dependencies'] ?? []) as $dependencyId => $range) {
            if (is_string($dependencyId) && is_string($range)) {
                $dependencies[$dependencyId] = $range;
            }
        }

        return $dependencies;
    }

    private function validateFrontendUiMode(mixed $mode, string $manifestPath): void
    {
        if ($mode === null) {
            return;
        }
        if (! is_string($mode) || ! in_array(trim($mode), ['overlay', 'embedded'], true)) {
            throw new PluginException(
                "Manifest {$manifestPath}: contributes.frontend.ui.mode must be 'overlay' or 'embedded'.",
            );
        }
    }
}
