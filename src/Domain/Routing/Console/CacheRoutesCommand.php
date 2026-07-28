<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\ClientRouteRepository;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\PluginRouteRepository;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\SystemRouteRepository;
use Polymorph\Platform\Domain\Routing\Services\Cache\RouteCache;

/**
 * Команда для прогрева кэша динамических маршрутов.
 *
 * Загружает все деревья маршрутов (системные из конфигов и клиентские из БД)
 * и сохраняет их в кэш для ускорения последующих запросов.
 *
 * Прогревает:
 * - Кэш системных роутов (SYSTEM и PLUGIN из конфигов)
 * - Кэш клиентских роутов (CLIENT из таблицы route_nodes)
 */
class CacheRoutesCommand extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'routing:cache';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Warm up the cache for dynamic routes';

    /**
     * Выполнить консольную команду.
     *
     * Прогревает кэш всех трёх источников дерева: system (файлы),
     * plugin (declarative из плагинов) и client (таблица route_nodes).
     *
     * @return int Код возврата (0 = успех, 1 = ошибка)
     */
    public function handle(
        SystemRouteRepository $systemRepository,
        PluginRouteRepository $pluginRepository,
        ClientRouteRepository $clientRepository,
        RouteCache $routeCache,
    ): int {
        $this->info('Warming up dynamic routes cache...');

        try {
            // Сначала очищаем весь кэш для принудительного обновления
            $this->info('  -> Clearing existing cache...');
            $routeCache->forgetTree();

            // Греем ИМЕННО enabled-деревья: на боевом пути регистрации читаются
            // они (MergedRouteTreeService::getEnabledTree), и у них отдельные
            // ключи кэша. Прогрев полного дерева оставил бы рантайм холодным.
            $total = 0;

            foreach ([
                'system' => $systemRepository,
                'plugin' => $pluginRepository,
                'client' => $clientRepository,
            ] as $label => $repository) {
                $this->info("  -> Loading {$label} routes...");
                $count = $repository->getEnabledTree()->count();
                $total += $count;
                $this->info("  OK {$label} routes cached: {$count} node(s)");
            }

            $this->info("Cache warmed up successfully. Total: {$total} route node(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to warm up cache: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
