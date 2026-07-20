<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException as PluginException;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;

final class ExtensionMigrationService
{
    /**
     * @return list<string>
     */
    public function discoverMigrationPaths(): array
    {
        $paths = [];

        foreach (app(ExtensionDiscoveryService::class)->discoverAll() as $plugin) {
            $migrationPath = $this->resolveMigrationPath($plugin);
            if ($migrationPath !== null && is_dir($migrationPath)) {
                $paths[] = $migrationPath;
            }
        }

        return $paths;
    }

    public function runMigrations(DiscoveredExtension $plugin): void
    {
        $migrationPath = $this->resolveMigrationPath($plugin);
        if ($migrationPath === null || ! is_dir($migrationPath)) {
            return;
        }

        $this->assertMigrationPrefixes($plugin, $migrationPath);

        $exitCode = Artisan::call('migrate', [
            '--path' => $this->relativeFromBasePath($migrationPath),
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            $output = trim(Artisan::output());
            throw new PluginException(sprintf(
                "Plugin '%s' migrations failed with exit code %d.%s",
                $plugin->id,
                $exitCode,
                $output !== '' ? ' Output: '.$output : '',
            ));
        }
    }

    public function purge(DiscoveredExtension $plugin): void
    {
        $prefix = $plugin->tablePrefix;
        if ($prefix === '') {
            return;
        }

        $escapedPrefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix);
        $tables = DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = current_schema() AND tablename LIKE ? ESCAPE '\\'",
            [$escapedPrefix.'%'],
        );

        foreach ($tables as $row) {
            $table = (string) $row->tablename;
            DB::statement('DROP TABLE IF EXISTS "'.str_replace('"', '', $table).'" CASCADE');
        }

        $migrationPath = $this->resolveMigrationPath($plugin);
        if ($migrationPath !== null && is_dir($migrationPath)) {
            $names = array_map(
                static fn (string $file): string => basename($file, '.php'),
                glob($migrationPath.DIRECTORY_SEPARATOR.'*.php') ?: [],
            );

            if ($names !== []) {
                DB::table('migrations')->whereIn('migration', $names)->delete();
            }
        }
    }

    private function resolveMigrationPath(DiscoveredExtension $plugin): ?string
    {
        $manifest = json_decode((string) file_get_contents($plugin->manifestPath), true);
        if (! is_array($manifest)) {
            return null;
        }

        $path = data_get($manifest, 'db.migrationsPath');
        if (! is_string($path) || trim($path) === '') {
            $path = 'be/database/migrations';
        }

        return dirname($plugin->manifestPath).DIRECTORY_SEPARATOR.ltrim($path, '/\\');
    }

    private function assertMigrationPrefixes(DiscoveredExtension $plugin, string $migrationPath): void
    {
        $files = glob($migrationPath.DIRECTORY_SEPARATOR.'*.php') ?: [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (! is_string($content)) {
                continue;
            }

            preg_match_all("/create\\('([^']+)'/m", $content, $matches);
            /** @var array<int, string> $tables */
            $tables = $matches[1] ?? [];
            foreach ($tables as $tableName) {
                if (! str_starts_with($tableName, $plugin->tablePrefix)) {
                    throw new PluginException("Migration '{$file}' creates table '{$tableName}' without prefix '{$plugin->tablePrefix}'.");
                }
            }

            if (preg_match('/\bCONCURRENTLY\b/i', $content) === 1) {
                throw new PluginException(
                    "Migration '{$file}' uses CONCURRENTLY and cannot run inside plugin enable transaction.",
                );
            }
        }
    }

    private function relativeFromBasePath(string $path): string
    {
        $basePath = base_path();
        if (str_starts_with($path, $basePath)) {
            return ltrim(substr($path, strlen($basePath)), '/\\');
        }

        return $path;
    }
}
