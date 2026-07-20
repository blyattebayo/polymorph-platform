<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Loaders;

use Polymorph\Platform\Domain\Routing\Core\Enums\OwnerType;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Illuminate\Support\Collection;

/**
 * Сервис для загрузки системных декларативных маршрутов из файлов.
 *
 * Использует RouteDefinitionLoader для загрузки из routes/*.php в виде VO.
 *
 * @package Polymorph\Platform\Domain\Routing\Loaders
 */
class SystemRouteLoader
{
    /**
     * Конструктор.
     *
     * @param \Polymorph\Platform\Domain\Routing\Loaders\RouteDefinitionLoader $loader Базовый загрузчик маршрутов
     */
    public function __construct(
        private RouteDefinitionLoader $loader
    ) {}

    /**
     * Создать RouteNodeDefinition из массива конфигурации.
     *
     * @param array<string, mixed> $data Данные конфигурации
     * @return RouteNodeDefinition|null Созданный RouteNodeDefinition или null при ошибке
     */
    public function createFromArray(array $data): ?RouteNodeDefinition
    {
        // Поддерживаем прежнюю семантику: owner и sort_order приходят как есть из config.
        $data['enabled'] = $data['enabled'] ?? true;

        return $this->loader->createFromArray($data);
    }
    
    /**
     * Загрузить все системные декларативные маршруты.
     *
     * Загружает маршруты из всех файлов в routes/.
     * Порядок регистрации определяется sort_order, указанным в файлах:
     * - web_core.php: sort_order = -1000
     * - api.php: sort_order = -999
     * - api_admin.php: sort_order = -998
     *
     * @return \Illuminate\Support\Collection<int, RouteNodeDefinition> Коллекция всех RouteNodeDefinition
     */
    public function loadAll(): Collection
    {
        $allNodes = new Collection;
        $files = (array) config('routing.declarative_files', [
            'web_core.php',
            'api.php',
            'api_plugins.php',
            'api_admin.php',
        ]);

        foreach ($files as $file) {
            $config = $this->loader->loadFromFile($file);
            $nodes = $this->loader->convertToRouteNodes($config);

            foreach ($nodes as $node) {
                $allNodes->push(
                    $node->withOwnership(
                        OwnerType::make(OwnerType::SYSTEM),
                        $node->sortOrder,
                    )
                );
            }
        }

        return $allNodes;
    }
}
