<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Artifacts;

use Polymorph\Platform\Domain\Extensions\Artifacts\Contracts\ExtensionArtifactSource;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException;
use Polymorph\Platform\Domain\Extensions\Manifest\ManifestV2Validator;
use ZipArchive;

final class ExtensionArtifactInstaller
{
    public function __construct(
        private readonly ManifestV2Validator $manifestV2Validator,
    ) {}

    public function install(ExtensionArtifactSource $source): string
    {
        $artifact = $source->resolve();
        $this->verifyChecksum($artifact);

        $zip = new ZipArchive;
        if ($zip->open($artifact->zipPath) !== true) {
            throw new ExtensionException("Unable to open plugin artifact: {$artifact->zipPath}");
        }

        $manifest = $this->readManifest($zip, $artifact->zipPath);
        try {
            $this->manifestV2Validator->validate($manifest, "{$artifact->zipPath}#extension.json");
        } catch (\InvalidArgumentException $e) {
            throw new ExtensionException("Plugin artifact {$artifact->zipPath}: {$e->getMessage()}");
        }
        $pluginId = (string) $manifest['id'];

        $this->assertNoZipSlip($zip, $artifact->zipPath);

        $runtimeRoot = $this->runtimeRoot();
        $staging = $this->stagingRoot().DIRECTORY_SEPARATOR.$pluginId.'-'.uniqid();
        $this->removeDirectory($staging);

        $extracted = $zip->extractTo($staging);
        $zip->close();

        if (! $extracted) {
            $this->removeDirectory($staging);
            throw new ExtensionException("Failed to extract plugin artifact into staging: {$staging}");
        }

        $this->swapIntoPlace($staging, $runtimeRoot.DIRECTORY_SEPARATOR.$pluginId);

        return $pluginId;
    }

    private function verifyChecksum(ResolvedArtifact $artifact): void
    {
        if ($artifact->declaredChecksum === null) {
            return;
        }

        $actual = hash_file('sha256', $artifact->zipPath);
        if (! is_string($actual) || ! hash_equals(strtolower($artifact->declaredChecksum), strtolower($actual))) {
            throw new ExtensionException(
                "Plugin artifact checksum mismatch for {$artifact->zipPath}: expected {$artifact->declaredChecksum}, got ".(is_string($actual) ? $actual : 'n/a').'.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(ZipArchive $zip, string $zipPath): array
    {
        $raw = $zip->getFromName('extension.json');

        if ($raw === false || trim($raw) === '') {
            throw new ExtensionException("Plugin artifact {$zipPath} has no extension.json at its root.");
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new ExtensionException("Plugin artifact {$zipPath}: manifest is not valid JSON.");
        }

        return $decoded;
    }

    private function assertNoZipSlip(ZipArchive $zip, string $zipPath): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name)) {
                continue;
            }

            $normalized = str_replace('\\', '/', $name);
            if (str_starts_with($normalized, '/') || str_contains($normalized, '../')) {
                throw new ExtensionException("Plugin artifact {$zipPath} contains an unsafe path entry: {$name}");
            }
        }
    }

    private function swapIntoPlace(string $staging, string $target): void
    {
        $backup = null;

        if (is_dir($target)) {
            $backup = $target.'.backup-'.uniqid();
            if (! $this->renameWithRetry($target, $backup)) {
                $this->removeDirectory($staging);
                throw new ExtensionException($this->lockedDirMessage($target, 'move the existing plugin directory aside'));
            }
        }

        if (! $this->renameWithRetry($staging, $target)) {
            if (! $this->copyDir($staging, $target)) {
                if ($backup !== null) {
                    @rename($backup, $target);
                }
                $this->removeDirectory($staging);
                throw new ExtensionException($this->lockedDirMessage($target, 'move the new plugin artifact into place'));
            }
            $this->removeDirectory($staging);
        }

        if ($backup !== null) {
            $this->removeDirectory($backup);
        }
    }

    private function copyDir(string $from, string $to): bool
    {
        if (! is_dir($from)) {
            return false;
        }
        if (! is_dir($to) && ! @mkdir($to, 0755, true) && ! is_dir($to)) {
            return false;
        }

        foreach (scandir($from) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $src = $from.DIRECTORY_SEPARATOR.$name;
            $dst = $to.DIRECTORY_SEPARATOR.$name;
            if (is_link($src)) {
                continue;
            }
            if (is_dir($src)) {
                if (! $this->copyDir($src, $dst)) {
                    return false;
                }
            } elseif (! @copy($src, $dst)) {
                return false;
            }
        }

        return true;
    }

    private function stagingRoot(): string
    {
        $root = rtrim((string) config('plugins.staging_path'), '/\\');
        if ($root === '') {
            $root = rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.'polymorph-plugin-staging';
        }

        if (! is_dir($root) && ! mkdir($root, 0755, true) && ! is_dir($root)) {
            throw new ExtensionException("Unable to create plugin staging dir: {$root}");
        }

        return $root;
    }

    private function renameWithRetry(string $from, string $to, int $attempts = 5): bool
    {
        for ($i = 0; $i < $attempts; $i++) {
            if (@rename($from, $to)) {
                return true;
            }
            usleep(50_000 * ($i + 1));
        }

        return false;
    }

    private function lockedDirMessage(string $target, string $what): string
    {
        return sprintf(
            'Failed to %s: %s. The directory is likely held by a process (IDE file watcher, dev server, antivirus). Stop watchers on the plugins directory and retry.',
            $what,
            $target,
        );
    }

    private function runtimeRoot(): string
    {
        $root = rtrim((string) config('plugins.root_path'), '/\\');
        if ($root === '') {
            throw new ExtensionException('plugins.root_path is not configured.');
        }

        if (! is_dir($root) && ! mkdir($root, 0755, true) && ! is_dir($root)) {
            throw new ExtensionException("Unable to create plugins root: {$root}");
        }

        return $root;
    }

    private function removeDirectory(string $path): void
    {
        if (is_link($path)) {
            $this->unlinkPath($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (! is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path.DIRECTORY_SEPARATOR.$item;
            if (is_link($child)) {
                $this->unlinkPath($child);
            } elseif (is_dir($child)) {
                $this->removeDirectory($child);
            } else {
                @unlink($child);
            }
        }

        @rmdir($path);
    }

    private function unlinkPath(string $path): void
    {
        if (@unlink($path)) {
            return;
        }

        @rmdir($path);
    }
}
