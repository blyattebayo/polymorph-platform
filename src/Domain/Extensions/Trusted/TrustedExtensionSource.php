<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Trusted;

use ReflectionClass;

/**
 * Resolves the host's trusted-plugin list (config('plugins.trusted') — the withPlugins([...])
 * declaration) into the package directories + extension ids the rest of the extension pipeline
 * needs. Trusted plugins ship as Composer packages in vendor/, so they are located by reflecting
 * on their ExtensionProvider FQCN and walking up to the directory that holds the manifest
 * (extension.json / plugin.json) — the same $directory shape ExtensionDiscoveryService expects
 * (ADR 0006 Стадия 4). Resolution is computed live from config each call — the trusted list is
 * tiny (a handful of vetted packages), so caching is not worth the staleness risk under tests.
 */
final class TrustedExtensionSource
{
    private const MANIFESTS = ['extension.json', 'plugin.json'];

    /**
     * Provider FQCNs declared trusted by the host.
     *
     * @return list<string>
     */
    public function providerClasses(): array
    {
        $trusted = config('plugins.trusted', []);

        return is_array($trusted)
            ? array_values(array_filter($trusted, static fn ($p): bool => is_string($p) && $p !== ''))
            : [];
    }

    /**
     * Package root directories (each containing a manifest + be/) for the trusted plugins.
     *
     * @return list<string>
     */
    public function directories(): array
    {
        $dirs = [];
        foreach ($this->providerClasses() as $providerClass) {
            $dir = $this->resolveDirectory($providerClass);
            if ($dir !== null) {
                $dirs[$dir] = true;
            }
        }

        return array_keys($dirs);
    }

    /**
     * Extension ids of the trusted plugins (read from their manifest 'id' field).
     *
     * @return list<string>
     */
    public function ids(): array
    {
        $ids = [];
        foreach ($this->directories() as $dir) {
            $id = $this->readManifestId($dir);
            if ($id !== null) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    public function isTrusted(string $extensionId): bool
    {
        return in_array($extensionId, $this->ids(), true);
    }

    private function resolveDirectory(string $providerClass): ?string
    {
        if (! class_exists($providerClass)) {
            return null;
        }

        $file = (new ReflectionClass($providerClass))->getFileName();
        if ($file === false) {
            return null;
        }

        // Walk up from the provider file until a manifest is found (the package root).
        $dir = dirname($file);
        for ($i = 0; $i < 6; $i++) {
            foreach (self::MANIFESTS as $manifest) {
                if (is_file($dir.'/'.$manifest)) {
                    return $dir;
                }
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return null;
    }

    private function readManifestId(string $dir): ?string
    {
        foreach (self::MANIFESTS as $manifest) {
            $path = $dir.'/'.$manifest;
            if (! is_file($path)) {
                continue;
            }
            $decoded = json_decode((string) file_get_contents($path), true);
            $id = is_array($decoded) ? ($decoded['id'] ?? null) : null;

            return is_string($id) && $id !== '' ? $id : null;
        }

        return null;
    }
}
