<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Plugin;

/**
 * Порт: откуда роутинг узнаёт о маршрутах включённых расширений.
 *
 * Отдаёт ПУТИ, а не исполненный конфиг: файл расширения исполняется ровно
 * один раз и ровно в одном месте — в {@see PluginRoutes::fromFile()}, где
 * стоит единственная проверка типа. Реализуется доменом Extensions, поэтому
 * роутинг о нём не знает.
 */
interface PluginRouteCatalog
{
    /**
     * Файлы маршрутов включённых расширений в детерминированном порядке.
     *
     * @return list<PluginRouteFile>
     */
    public function enabled(): array;
}
