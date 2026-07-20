<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\ClientRouteRepository;
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
     * Прогревает кэш для обоих репозиториев:
     * 1. SystemRouteRepository - системные и плагинные роуты из конфигов
     * 2. ClientRouteRepository - клиентские роуты из БД
     *
     * @param  SystemRouteRepository  $systemRepository  Репозиторий системных роутов
     * @param  ClientRouteRepository  $clientRepository  Репозиторий клиентских роутов
     * @param  RouteCache  $routeCache  Сервис кэширования
     * @return int Код возврата (0 = успех, 1 = ошибка)
     */
    public function handle(
        SystemRouteRepository $systemRepository,
        ClientRouteRepository $clientRepository,
        RouteCache $routeCache,
    ): int {
        $this->info('Warming up dynamic routes cache...');

        try {
            // Сначала очищаем весь кэш для принудительного обновления
            $this->info('  в†’ Clearing existing cache...');
            $routeCache->forgetTree();

            // Прогрев кэша системных роутов (SYSTEM + PLUGIN)
            $this->info('  в†’ Loading system routes...');
            $systemTree = $systemRepository->getTree();
            $systemCount = $systemTree->count();
            $this->info("  ✓ System routes cached: {$systemCount} node(s)");

            // Прогрев кэша клиентских роутов (CLIENT)
            $this->info('  в†’ Loading client routes...');
            $clientTree = $clientRepository->getTree();
            $clientCount = $clientTree->count();
            $this->info("  ✓ Client routes cached: {$clientCount} node(s)");

            $totalCount = $systemCount + $clientCount;
            $this->info("Cache warmed up successfully. Total: {$totalCount} route node(s).");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to warm up cache: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
