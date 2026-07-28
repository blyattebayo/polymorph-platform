<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Routing;

use Polymorph\Platform\Domain\Extensions\Core\Models\ExtensionRegistry;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;
use Polymorph\Platform\Domain\Routing\Plugin\PluginRouteCatalog;
use Polymorph\Platform\Domain\Routing\Plugin\PluginRouteFile;
use Throwable;

/**
 * Каталог файлов маршрутов включённых расширений для движка v2.
 *
 * Отдаёт ПУТИ, а не исполненный конфиг: файл расширения исполняется ровно
 * один раз, в PluginRoutes::fromFile(). Направление зависимости инвертировано —
 * роутинг знает только порт, реализация живёт в домене Extensions.
 */
final class ExtensionRouteFileCatalog implements PluginRouteCatalog
{
    public function __construct(
        private readonly ExtensionDiscoveryService $discovery,
    ) {}

    /**
     * @return list<PluginRouteFile>
     */
    public function enabled(): array
    {
        $enabled = $this->enabledIds();
        $files = [];

        try {
            $extensions = $this->discovery->discoverAll();
        } catch (Throwable) {
            // Причину уже записал сам обход. Маршрутизация ядра не может
            // зависеть от того, что лежит в каталоге расширений.
            return [];
        }

        foreach ($extensions as $extension) {
            if (! isset($enabled[$extension->id]) || $extension->backendRouteFile === null) {
                continue;
            }

            $files[] = new PluginRouteFile($extension->id, $extension->backendRouteFile);
        }

        // Порядок регистрации не должен зависеть от порядка обхода каталога
        // или строк в таблице: между инсталляциями он разный.
        usort($files, static fn (PluginRouteFile $a, PluginRouteFile $b): int => strcmp($a->pluginId, $b->pluginId));

        return $files;
    }

    /**
     * @return array<string, true>
     */
    private function enabledIds(): array
    {
        try {
            $ids = ExtensionRegistry::query()
                ->where('is_enabled', true)
                ->pluck('plugin_id')
                ->all();
        } catch (Throwable) {
            // Роутер поднимается на КАЖДОМ бутстрапе, включая artisan migrate
            // на чистом хосте, где таблицы реестра ещё нет.
            return [];
        }

        $map = [];
        foreach ($ids as $id) {
            if (is_string($id)) {
                $map[$id] = true;
            }
        }

        return $map;
    }
}
