<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Console;

use Polymorph\Platform\Domain\Routing\Services\Cache\RouteCache;
use Illuminate\Console\Command;

/**
 * Команда для очистки кэша динамических маршрутов.
 *
 * Удаляет закэшированное дерево маршрутов, что приведёт к перезагрузке
 * данных из базы данных при следующем запросе.
 *
 * @package Polymorph\Platform\Domain\Routing\Console
 */
class ClearRouteCacheCommand extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'routing:clear';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Clear the cache for dynamic routes';

    /**
     * Выполнить консольную команду.
     *
     * Очищает кэш дерева маршрутов через RouteCache.
     *
     * @param \Polymorph\Platform\Domain\Routing\Services\Cache\RouteCache $cache Сервис кэширования маршрутов
     * @return int Код возврата (0 = успех, 1 = ошибка)
     */
    public function handle(RouteCache $cache): int
    {
        $this->info('Clearing dynamic routes cache...');

        try {
            $cache->forgetTree();

            $this->info('Cache cleared successfully.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to clear cache: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
