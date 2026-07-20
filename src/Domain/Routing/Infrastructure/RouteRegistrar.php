<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure;

use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Domain\Routing\Infrastructure\Registrars\RouteNodeRegistrarFactory;
use Polymorph\Platform\Domain\Routing\Services\MergedRouteTreeService;

/**
 * Сервис для регистрации всех маршрутов приложения.
 *
 * Отвечает за создание и настройку всех компонентов динамической маршрутизации
 * и регистрацию маршрутов в Laravel Router.
 */
final class RouteRegistrar
{
    public function __construct(
        private MergedRouteTreeService $mergedTreeService,
        private RouteNodeRegistrarFactory $registrarFactory,
    ) {}

    /**
     * Зарегистрировать все маршруты (системные и клиентские).
     */
    public function registerAllRoutes(): void
    {
        $this->registerNodes($this->mergedTreeService->getEnabledTree());
    }

    /**
     * Зарегистрировать набор корневых узлов.
     *
     * @param  iterable<RouteNodeDefinition>  $nodes
     */
    public function registerNodes(iterable $nodes): void
    {
        foreach ($nodes as $definition) {
            $registrar = $this->registrarFactory->create($definition->kind);

            if ($registrar !== null) {
                $registrar->register($definition);
            }
        }
    }
}
