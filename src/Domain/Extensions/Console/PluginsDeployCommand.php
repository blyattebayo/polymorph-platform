<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Illuminate\Console\Command;

final class PluginsDeployCommand extends Command
{
    protected $signature = 'plugins:deploy
        {id : Plugin identifier (folder name in plugins src root)}
        {--skip-install : Do not run npm install in fe/}
        {--skip-fe : Do not build the FE bundle}
        {--skip-composer : Do not run composer install in be/}';

    protected $description = 'Build a plugin artifact from sources and install it (local convenience: build + install).';

    public function handle(): int
    {
        $id = trim((string) $this->argument('id'));

        $buildExit = $this->call('plugins:build', [
            'id' => $id,
            '--skip-install' => (bool) $this->option('skip-install'),
            '--skip-fe' => (bool) $this->option('skip-fe'),
            '--skip-composer' => (bool) $this->option('skip-composer'),
        ]);

        if ($buildExit !== self::SUCCESS) {
            return self::FAILURE;
        }

        $srcDir = rtrim((string) config('plugins.src_root'), '/\\') . DIRECTORY_SEPARATOR . $id;
        $manifestPath = $srcDir . DIRECTORY_SEPARATOR . 'extension.json';
        $manifest = is_file($manifestPath)
            ? json_decode((string) file_get_contents($manifestPath), true)
            : null;
        $version = is_array($manifest) ? (string) ($manifest['version'] ?? '') : '';

        $zipPath = rtrim((string) config('plugins.build_output'), '/\\')
            . DIRECTORY_SEPARATOR . "{$id}-{$version}.zip";

        return $this->call('plugins:install', ['source' => $zipPath]) === self::SUCCESS
            ? self::SUCCESS
            : self::FAILURE;
    }
}
