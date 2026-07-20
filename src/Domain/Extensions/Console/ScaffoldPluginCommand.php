<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Polymorph\Platform\Support\Validation\ValidationConstraints;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

final class ScaffoldPluginCommand extends Command
{
    protected $signature = 'plugins:scaffold
        {id : Plugin identifier (slug, e.g. demo_plugin)}
        {--title= : Human-readable plugin title}
        {--force : Overwrite existing plugin directory}
        {--skip-install : Do not run npm install in fe/}';

    protected $description = 'Generate a new plugin from the canonical template into the SOURCES root (plugins-src). Build & activate it separately with plugins:deploy.';

    public function handle(): int
    {
        $pluginId = trim((string) $this->argument('id'));
        $slug = ValidationConstraints::slug();
        if (! $slug->matches($pluginId)) {
            $this->error("Invalid plugin id '{$pluginId}'. Expected pattern: {$slug->pattern()}");

            return self::FAILURE;
        }

        $title = trim((string) ($this->option('title') ?? ''));
        if ($title === '') {
            $title = Str::of($pluginId)->replace('_', ' ')->title()->toString();
        }

        $pluginsRoot = rtrim((string) config('plugins.src_root'), '/\\');
        $templateRoot = rtrim((string) config('plugins.scaffold_template_path'), '/\\');
        $targetRoot = $pluginsRoot . DIRECTORY_SEPARATOR . $pluginId;

        if (! is_dir($templateRoot)) {
            $this->error("Template directory not found: {$templateRoot}");

            return self::FAILURE;
        }

        if (is_dir($targetRoot)) {
            if (! (bool) $this->option('force')) {
                $this->error("Plugin directory already exists: {$targetRoot}. Use --force to overwrite.");

                return self::FAILURE;
            }
            $this->removeDirectory($targetRoot);
        }

        $pluginClass = $this->studlyPluginClass($pluginId);
        $tokens = [
            '__PLUGIN_ID__' => $pluginId,
            '__PLUGIN_NAME__' => $title,
            '__PLUGIN_CLASS__' => $pluginClass,
            '__PLUGIN_SCOPE__' => $pluginId . '_remote',
            '__PLUGIN_MOUNT_PATH__' => '/plugins/' . $pluginId,
            '__PLUGIN_TABLE_PREFIX__' => 'plg_' . $pluginId . '_',
            '__PLUGIN_PACKAGE__' => 'polymorph/' . str_replace('_', '-', $pluginId) . '-plugin',
        ];

        $this->copyTemplate($templateRoot, $targetRoot, $tokens);
        $this->info("Plugin '{$pluginId}' scaffolded in {$targetRoot}.");

        $feDir = $targetRoot . DIRECTORY_SEPARATOR . 'fe';

        if (! $this->option('skip-install') && is_dir($feDir)) {
            if (! $this->runFeProcess($feDir, 'npm install', 'Installing FE dependencies')) {
                $this->warn("npm install failed. Finish manually: cd \"{$feDir}\" && npm install");
            }
        }

        $this->info("Plugin '{$pluginId}' is ready for development in {$targetRoot}.");
        $this->line('Next steps:');
        $this->line("  1) develop in {$targetRoot}");
        $this->line("  2) build + install the drop-in: php artisan plugins:deploy {$pluginId}");

        return self::SUCCESS;
    }

    private function runFeProcess(string $feDir, string $command, string $label): bool
    {
        if (! is_dir($feDir)) {
            $this->error("FE directory not found: {$feDir} (template has no fe/?).");

            return false;
        }

        $this->line("{$label} ({$command})…");
        $result = Process::path($feDir)
            ->timeout(600)
            ->run($command, function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });

        if (! $result->successful()) {
            $this->error("`{$command}` failed in {$feDir}.");

            return false;
        }

        return true;
    }

    /**
     * @param array<string, string> $tokens
     */
    private function copyTemplate(string $sourceDir, string $targetDir, array $tokens): void
    {
        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
            throw new \RuntimeException("Unable to create directory {$targetDir}");
        }

        $entries = scandir($sourceDir);
        if (! is_array($entries)) {
            throw new \RuntimeException("Unable to read template directory {$sourceDir}");
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $entry;
            $targetName = str_replace(array_keys($tokens), array_values($tokens), $entry);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $targetName;

            if (is_dir($sourcePath)) {
                $this->copyTemplate($sourcePath, $targetPath, $tokens);
                continue;
            }

            $contents = file_get_contents($sourcePath);
            if (! is_string($contents)) {
                throw new \RuntimeException("Unable to read template file {$sourcePath}");
            }

            $contents = str_replace(array_keys($tokens), array_values($tokens), $contents);
            if (file_put_contents($targetPath, $contents) === false) {
                throw new \RuntimeException("Unable to write scaffold file {$targetPath}");
            }
        }
    }

    private function removeDirectory(string $path): void
    {
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

            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) {
                $this->removeDirectory($child);
            } elseif (is_file($child)) {
                unlink($child);
            }
        }

        rmdir($path);
    }

    private function studlyPluginClass(string $pluginId): string
    {
        return Str::of($pluginId)
            ->replace(['-', '_'], ' ')
            ->title()
            ->replace(' ', '')
            ->toString();
    }
}
