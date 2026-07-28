<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Plugin;

/**
 * Файл маршрутов одного расширения.
 */
final readonly class PluginRouteFile
{
    public function __construct(
        public string $pluginId,
        public string $path,
    ) {}
}
