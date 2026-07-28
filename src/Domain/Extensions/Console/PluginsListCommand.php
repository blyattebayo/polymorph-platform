<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionManager;
use Polymorph\Platform\Domain\Routing\Plugin\PluginRouteMounter;

final class PluginsListCommand extends Command
{
    protected $signature = 'plugins:list {--sync : Sync manifests before listing}';

    protected $description = 'List plugins and their lifecycle status.';

    public function handle(
        ExtensionManager $pluginManager,
        ExtensionDiscoveryService $discovery,
        PluginRouteMounter $mounter,
    ): int {
        if ((bool) $this->option('sync')) {
            $pluginManager->discoverAndSync();
        }

        $rows = $pluginManager->listRegistry()->map(static function ($plugin): array {
            return [
                $plugin->plugin_id,
                $plugin->version,
                $plugin->is_enabled ? 'enabled' : 'disabled',
                $plugin->last_warning ?? '',
            ];
        })->all();

        $this->table(['Plugin', 'Version', 'Status', 'Warning'], $rows);

        $ok = $this->reportMountFailures($mounter);

        return $this->reportDiscoveryFailures($discovery) === self::SUCCESS && $ok === self::SUCCESS
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * Расширения, которые числятся включёнными, но чьи маршруты не смонтированы.
     *
     * Самый частый случай — обновление ядра: установленный артефакт собран
     * против старого SDK. В реестре при этом «enabled», а по факту 404,
     * и без этой строчки единственным следом остаётся запись в логе.
     */
    private function reportMountFailures(PluginRouteMounter $mounter): int
    {
        $failures = $mounter->failures();

        if ($failures === []) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error(sprintf('Включено, но маршруты не смонтированы: %d', count($failures)));

        foreach ($failures as $pluginId => $reason) {
            $this->line("  {$pluginId}");
            $this->line("    {$reason}");
        }

        return self::FAILURE;
    }

    /**
     * Пропущенные при обходе расширения.
     *
     * Обход больше не падает от одного битого манифеста, поэтому «поставил,
     * а его нет в списке» обязано иметь видимое объяснение — иначе тихий
     * пропуск просто заменит собой прежнее громкое падение.
     *
     * Ненулевой код возврата: в CI и в деплой-скриптах это должно быть
     * заметно без чтения логов.
     */
    private function reportDiscoveryFailures(ExtensionDiscoveryService $discovery): int
    {
        // discoverAll() наполняет список пропусков; без --sync он ещё не звучал.
        $discovery->discoverAll();

        $failures = $discovery->failures();

        if ($failures === []) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error(sprintf('Пропущено расширений при обходе: %d', count($failures)));

        foreach ($failures as $failure) {
            $this->line("  {$failure->path}");
            $this->line("    {$failure->reason}");
        }

        return self::FAILURE;
    }
}
