<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Observers;

use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;
use Polymorph\Platform\Domain\Routing\Services\Cache\RouteCache;

/**
 * Observer для модели RouteNode.
 *
 * Обрабатывает события жизненного цикла RouteNode:
 * - Автоматическая инвалидация кэша дерева маршрутов при изменениях
 */
class RouteNodeObserver
{
    /**
     * @param  RouteCache  $cache  Сервис кэширования маршрутов
     */
    public function __construct(
        private RouteCache $cache,
    ) {}

    /**
     * Обработать событие "saved" для RouteNode.
     *
     * Инвалидирует кэш дерева маршрутов при создании или обновлении узла.
     * Использует forgetClientTree() для очистки только клиентского кэша,
     * сохраняя кэш системных роутов (которые статичны и не меняются).
     *
     * @param  RouteNode  $node  Сохранённый узел
     */
    public function saved(RouteNode $node): void
    {
        $this->cache->forgetClientTree();
    }

    /**
     * Обработать событие "deleted" для RouteNode.
     *
     * Инвалидирует кэш дерева маршрутов при удалении узла (включая soft delete).
     * Использует forgetClientTree() для очистки только клиентского кэша,
     * сохраняя кэш системных роутов.
     *
     * @param  RouteNode  $node  Удалённый узел
     */
    public function deleted(RouteNode $node): void
    {
        $this->cache->forgetClientTree();
    }

    /**
     * Обработать событие "restored" для RouteNode.
     *
     * Инвалидирует кэш дерева маршрутов при восстановлении узла из soft delete.
     * Использует forgetClientTree() для очистки только клиентского кэша,
     * сохраняя кэш системных роутов.
     *
     * @param  RouteNode  $node  Восстановленный узел
     */
    public function restored(RouteNode $node): void
    {
        $this->cache->forgetClientTree();
    }
}
