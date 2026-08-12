<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\Extensions\Manifest\ManifestV2Validator;
use ZipArchive;

final class PluginsBuildCommand extends Command
{
    protected $signature = 'plugins:build
        {id : Plugin identifier (folder name in plugins src root)}
        {--skip-install : Do not run npm install in fe/}
        {--skip-fe : Do not build the FE bundle}
        {--skip-composer : Do not run composer install in be/}
        {--skip-scope : Do not isolate plugin vendor with php-scoper}';

    protected $description = 'Build a drop-in .zip artifact from plugin sources (FE bundle + BE deps), ready for plugins:install.';

    public function handle(ManifestV2Validator $v2Validator): int
    {
        $id = trim((string) $this->argument('id'));
        $srcRoot = rtrim((string) config('plugins.src_root'), '/\\');
        $srcDir = $srcRoot.DIRECTORY_SEPARATOR.$id;

        if (! is_dir($srcDir)) {
            $this->error("Plugin source directory not found: {$srcDir}");

            return self::FAILURE;
        }

        $manifestFile = 'extension.json';
        $manifestPath = $srcDir.DIRECTORY_SEPARATOR.$manifestFile;

        if (! is_file($manifestPath)) {
            $this->error("No manifest found in source (extension.json): {$srcDir}");

            return self::FAILURE;
        }

        if (! is_file($srcDir.DIRECTORY_SEPARATOR.'composer.json')) {
            $this->error("Plugin source must contain composer.json: {$srcDir}");

            return self::FAILURE;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            $this->error("Manifest is not valid JSON: {$manifestPath}");

            return self::FAILURE;
        }

        try {
            $v2Validator->validate($manifest, $manifestPath);
        } catch (\Throwable $exception) {
            $this->error('Manifest validation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $version = (string) $manifest['version'];
        $feDir = $srcDir.DIRECTORY_SEPARATOR.'fe';

        if (is_dir($feDir) && ! (bool) $this->option('skip-fe')) {
            if (! (bool) $this->option('skip-install') && ! $this->runProcess($feDir, 'npm install', 'Installing FE dependencies')) {
                return self::FAILURE;
            }
            if (! $this->runProcess($feDir, 'npm run build', 'Building FE bundle')) {
                return self::FAILURE;
            }
        }

        if (! (bool) $this->option('skip-composer')) {
            if (! $this->runProcess(
                $srcDir,
                'composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs',
                'Installing BE dependencies (mirroring path repos)',
                ['COMPOSER_MIRROR_PATH_REPOS' => '1'],
            )) {
                return self::FAILURE;
            }
        }

        $beVendorRoot = $this->scopePluginVendor($id, $srcDir);
        if ($beVendorRoot === null) {
            return self::FAILURE;
        }
        if (! is_file($beVendorRoot.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
            $this->error('Plugin artifact requires vendor/autoload.php; run Composer or remove --skip-composer.');

            return self::FAILURE;
        }

        $zipPath = $this->packageArtifact($id, $version, $srcDir, $beVendorRoot, $manifestFile);
        if ($zipPath === null) {
            return self::FAILURE;
        }

        if ($beVendorRoot !== $srcDir) {
            $this->deleteDir($beVendorRoot);
        }

        $checksum = hash_file('sha256', $zipPath);
        file_put_contents($zipPath.'.sha256', $checksum."  {$id}-{$version}.zip\n");

        $this->info("Built artifact: {$zipPath}");
        $this->line("sha256: {$checksum}");
        $this->line("Install it with: php artisan plugins:install \"{$zipPath}\"");

        return self::SUCCESS;
    }

    private function packageArtifact(string $id, string $version, string $srcDir, string $beVendorRoot, string $manifestFile = 'extension.json'): ?string
    {
        $outputRoot = rtrim((string) config('plugins.build_output'), '/\\');
        if ($outputRoot === '') {
            $this->error('plugins.build_output is not configured.');

            return null;
        }
        if (! is_dir($outputRoot) && ! mkdir($outputRoot, 0755, true) && ! is_dir($outputRoot)) {
            $this->error("Unable to create build output directory: {$outputRoot}");

            return null;
        }

        $distDir = $srcDir.DIRECTORY_SEPARATOR.'fe'.DIRECTORY_SEPARATOR.'dist';
        if (is_dir($srcDir.DIRECTORY_SEPARATOR.'fe') && ! is_dir($distDir)) {
            $this->error('FE present but fe/dist is missing — build the FE bundle first (omit --skip-fe).');

            return null;
        }

        $zipPath = $outputRoot.DIRECTORY_SEPARATOR."{$id}-{$version}.zip";
        @unlink($zipPath);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Unable to create zip: {$zipPath}");

            return null;
        }

        $zip->addFile($srcDir.DIRECTORY_SEPARATOR.$manifestFile, $manifestFile);
        $composerManifest = $srcDir.DIRECTORY_SEPARATOR.'composer.json';
        if (is_file($composerManifest)) {
            $zip->addFile($composerManifest, 'composer.json');
        }
        $this->addTree($zip, $beVendorRoot.DIRECTORY_SEPARATOR.'be', 'be', ['tests']);
        $this->addTree(
            $zip,
            $beVendorRoot.DIRECTORY_SEPARATOR.'vendor',
            'vendor',
            ['test', 'tests', 'Test', 'Tests'],
        );
        $this->addTree($zip, $distDir, 'fe/dist');

        $zip->close();

        return $zipPath;
    }

    private function scopePluginVendor(string $id, string $srcDir): ?string
    {
        $vendorDir = $srcDir.DIRECTORY_SEPARATOR.'vendor';
        if ((bool) $this->option('skip-scope') || ! is_dir($vendorDir)) {
            return $srcDir;
        }

        $phar = (string) config('plugins.php_scoper_path');
        $config = (string) config('plugins.php_scoper_config');
        if (! is_file($phar) || ! is_file($config)) {
            $this->error("php-scoper not available (phar: {$phar}, config: {$config}). Install it or pass --skip-scope.");

            return null;
        }

        $prefix = rtrim((string) config('plugins.php_scoper_prefix_base'), '\\').'\\'.Str::studly($id);

        $outputRoot = rtrim((string) config('plugins.build_output'), '/\\');
        if (! is_dir($outputRoot) && ! mkdir($outputRoot, 0755, true) && ! is_dir($outputRoot)) {
            $this->error("Unable to create build output directory: {$outputRoot}");

            return null;
        }
        $scopeOut = $outputRoot.DIRECTORY_SEPARATOR.'.scope-'.$id.'-'.uniqid();
        $this->deleteDir($scopeOut);

        $scoped = $this->runProcess(
            $srcDir,
            sprintf(
                'php "%s" add-prefix --config="%s" --output-dir="%s" --working-dir="%s" --force --no-interaction --quiet',
                $phar,
                $config,
                $scopeOut,
                $srcDir,
            ),
            "Isolating plugin vendor (php-scoper, prefix {$prefix})",
            ['POLYMORPH_SCOPER_PREFIX' => $prefix],
        );
        if (! $scoped) {
            $this->deleteDir($scopeOut);

            return null;
        }

        foreach (['composer.json', 'composer.lock'] as $f) {
            if (is_file($srcDir.DIRECTORY_SEPARATOR.$f)) {
                @copy($srcDir.DIRECTORY_SEPARATOR.$f, $scopeOut.DIRECTORY_SEPARATOR.$f);
            }
        }
        if (! $this->runProcess(
            $scopeOut,
            'composer dump-autoload --classmap-authoritative --no-interaction --ignore-platform-reqs',
            'Regenerating scoped autoloader',
        )) {
            $this->deleteDir($scopeOut);

            return null;
        }

        return $scopeOut;
    }

    private function deleteDir(string $path): void
    {
        if (! is_dir($path) && ! is_link($path)) {
            return;
        }
        if (is_link($path)) {
            @unlink($path) || @rmdir($path);

            return;
        }

        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $child = $path.DIRECTORY_SEPARATOR.$name;
            if (is_link($child)) {
                @unlink($child) || @rmdir($child);
            } elseif (is_dir($child)) {
                $this->deleteDir($child);
            } else {
                @unlink($child);
            }
        }
        @rmdir($path);
    }

    /** @param list<string> $excludedDirectoryNames */
    private function addTree(ZipArchive $zip, string $dir, string $prefix, array $excludedDirectoryNames = []): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $zip->addEmptyDir($prefix);

        $entries = scandir($dir);
        if (! is_array($entries)) {
            return;
        }

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$name;
            $entry = $prefix.'/'.$name;

            if (is_link($path)) {
                $this->warn("Skipping symlink in artifact: {$path}");

                continue;
            }

            if (is_dir($path)) {
                if (in_array($name, $excludedDirectoryNames, true)) {
                    continue;
                }
                $this->addTree($zip, $path, $entry, $excludedDirectoryNames);
            } elseif (is_readable($path)) {
                $zip->addFile($path, $entry);
            } else {
                $this->warn("Skipping unreadable file in artifact: {$path}");
            }
        }
    }

    /**
     * @param  array<string, string>  $env
     */
    private function runProcess(string $dir, string $command, string $label, array $env = []): bool
    {
        $this->line("{$label} ({$command})…");
        $result = Process::path($dir)
            ->env($env)
            ->timeout(600)
            ->run($command, function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });

        if (! $result->successful()) {
            $this->error("`{$command}` failed in {$dir}.");

            return false;
        }

        return true;
    }
}
