<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Core\Contracts;

/**
 * Набор declarative-роутов одного включённого плагина: его id и конфиг,
 * совместимый с форматом route_nodes (как у системных файлов routes/*.php).
 */
final readonly class PluginRouteSet
{
    /**
     * @param list<array<string, mixed>> $config
     */
    public function __construct(
        public string $pluginId,
        public array $config,
    ) {}
}
