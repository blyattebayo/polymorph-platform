<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException as PluginException;
use Polymorph\Platform\Support\Validation\ValidationConstraints;

final class ExtensionManifestValidator
{
    public function __construct(
        private readonly ExtensionAclManifestParser $aclManifestParser,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function validate(array $manifest, string $manifestPath): void
    {
        $requiredStringFields = ['id', 'name', 'version', 'coreVersionRange'];
        foreach ($requiredStringFields as $field) {
            $value = $manifest[$field] ?? null;
            if (! is_string($value) || trim($value) === '') {
                throw new PluginException("Manifest {$manifestPath}: field '{$field}' must be non-empty string.");
            }
        }

        $pluginId = (string) $manifest['id'];
        $slug = ValidationConstraints::slug();
        if (! $slug->matches($pluginId)) {
            throw new PluginException("Manifest {$manifestPath}: plugin id must match ".$slug->pattern().'.');
        }

        $dbPrefix = data_get($manifest, 'db.tablePrefix');
        if (! is_string($dbPrefix) || trim($dbPrefix) === '') {
            throw new PluginException("Manifest {$manifestPath}: db.tablePrefix is required.");
        }

        if (! str_starts_with($dbPrefix, "plg_{$pluginId}_")) {
            throw new PluginException("Manifest {$manifestPath}: db.tablePrefix must start with 'plg_{$pluginId}_'.");
        }

        $this->validateDependencies($manifest, $manifestPath, $pluginId);

        $this->aclManifestParser->validate($manifest, $manifestPath);

        $frontendMountPath = data_get($manifest, 'frontend.mountPath');
        if ($frontendMountPath !== null) {
            if (! is_string($frontendMountPath) || ! str_starts_with($frontendMountPath, '/plugins/')) {
                throw new PluginException("Manifest {$manifestPath}: frontend.mountPath must start with '/plugins/'.");
            }
        }

        $frontendBundle = data_get($manifest, 'frontend.bundle');
        if ($frontendBundle !== null) {
            if (! is_string($frontendBundle) || trim($frontendBundle) === '') {
                throw new PluginException("Manifest {$manifestPath}: frontend.bundle must be non-empty string when provided.");
            }
        }

        $frontendUiMode = data_get($manifest, 'frontend.ui.mode');
        if ($frontendUiMode !== null) {
            if (! is_string($frontendUiMode) || ! in_array(trim($frontendUiMode), ['overlay', 'embedded'], true)) {
                throw new PluginException("Manifest {$manifestPath}: frontend.ui.mode must be 'overlay' or 'embedded' when provided.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function validateDependencies(array $manifest, string $manifestPath, string $pluginId): void
    {
        $slug = ValidationConstraints::slug();
        $dependencies = $manifest['dependencies'] ?? null;
        if ($dependencies === null) {
            return;
        }

        if (! is_array($dependencies)) {
            throw new PluginException("Manifest {$manifestPath}: dependencies must be an object {pluginId: versionRange}.");
        }

        foreach ($dependencies as $dependencyId => $range) {
            if (! is_string($dependencyId) || ! $slug->matches($dependencyId)) {
                throw new PluginException("Manifest {$manifestPath}: dependency id '{$dependencyId}' must match ".$slug->pattern().'.');
            }

            if ($dependencyId === $pluginId) {
                throw new PluginException("Manifest {$manifestPath}: plugin cannot depend on itself.");
            }

            if (! is_string($range) || trim($range) === '') {
                throw new PluginException("Manifest {$manifestPath}: dependency '{$dependencyId}' version range must be non-empty string.");
            }
        }
    }
}
