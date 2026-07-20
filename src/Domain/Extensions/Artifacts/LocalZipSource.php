<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Artifacts;

use Polymorph\Platform\Domain\Extensions\Artifacts\Contracts\ExtensionArtifactSource;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException;

final class LocalZipSource implements ExtensionArtifactSource
{
    public function __construct(
        private readonly string $zipPath,
        private readonly ?string $checksum = null,
    ) {
    }

    public function resolve(): ResolvedArtifact
    {
        $path = $this->zipPath;
        if (! is_file($path)) {
            throw new ExtensionException("Plugin artifact not found: {$path}");
        }

        if (strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'zip') {
            throw new ExtensionException("Plugin artifact must be a .zip file: {$path}");
        }

        return new ResolvedArtifact($path, $this->checksum ?? $this->readSidecarChecksum($path));
    }

    private function readSidecarChecksum(string $zipPath): ?string
    {
        $sidecar = $zipPath . '.sha256';
        if (! is_file($sidecar)) {
            return null;
        }

        $raw = trim((string) file_get_contents($sidecar));
        if ($raw === '') {
            return null;
        }

        $hex = preg_split('/\s+/', $raw)[0] ?? '';

        return is_string($hex) && $hex !== '' ? strtolower($hex) : null;
    }
}
