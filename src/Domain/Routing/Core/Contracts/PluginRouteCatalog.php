<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Core\Contracts;

/**
 * Порт: поставщик declarative-роутов включённых плагинов для домена Routing.
 *
 * Routing не знает про домен Plugins — он зависит только от этого контракта.
 * Реализуется адаптером в домене Plugins (знает про discovery/registry).
 * Так инвертируется направление зависимости: Plugins → Routing (порт),
 * а не Routing → Plugins (конкреты).
 */
interface PluginRouteCatalog
{
    /**
     * Наборы роутов всех включённых плагинов.
     *
     * @return list<PluginRouteSet>
     */
    public function enabledRouteSets(): array;

    /**
     * Fingerprint включённого набора (для версионирования кэша дерева).
     * Меняется при enable/disable или правке routes.php любого плагина.
     */
    public function fingerprint(): string;
}
