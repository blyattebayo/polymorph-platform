<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Registrars;

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Регистратор для GROUP узлов маршрутов.
 *
 * Регистрирует группы маршрутов через Route::group() с применением
 * атрибутов группы (prefix, domain, namespace, middleware, where)
 * и рекурсивной регистрацией дочерних узлов.
 */
class RouteGroupRegistrar implements RouteNodeRegistrarInterface
{
    /**
     * @param  \Polymorph\Platform\Domain\Routing\Registrars\RouteNodeRegistrarFactory|null  $registrarFactory  Фабрика для создания регистраторов дочерних узлов
     */
    public function __construct(
        private readonly AppLogger $logger,
        private ?RouteNodeRegistrarFactory $registrarFactory = null,
    ) {}

    /**
     * Зарегистрировать узел маршрута.
     *
     * @param  RouteNode  $node  Узел для регистрации
     */
    public function register(RouteNodeDefinition $node): void
    {
        $attributes = $this->buildGroupAttributes($node);

        Route::group($attributes, function () use ($node): void {
            $this->registerChildren($node);
        });
    }

    /**
     * Зарегистрировать дочерние узлы.
     *
     * @param  RouteNodeDefinition  $node  Родительский узел
     */
    private function registerChildren(RouteNodeDefinition $node): void
    {
        if ($this->registrarFactory === null) {
            $this->logger->error('routing.group.registrar_factory_missing', [
                'route_node_id' => $node->sourceId,
            ]);

            return;
        }

        foreach ($node->children as $child) {
            $registrar = $this->registrarFactory->create($child->kind);
            if ($registrar !== null) {
                $registrar->register($child);
            }
        }
    }

    /**
     * Построить атрибуты для группы маршрутов.
     *
     * @param  RouteNodeDefinition  $node  Узел группы
     * @return array<string, mixed> Атрибуты для Route::group()
     */
    private function buildGroupAttributes(RouteNodeDefinition $node): array
    {
        $attributes = [];

        if ($node->prefix) {
            $attributes['prefix'] = $node->prefix;
        }

        if ($node->domain) {
            $attributes['domain'] = $node->domain;
        }

        if ($node->namespace) {
            $attributes['namespace'] = $node->namespace;
        }

        if (! empty($node->middleware)) {
            $attributes['middleware'] = $node->middleware;
        }

        if ($node->where) {
            $attributes['where'] = $node->where;
        }

        return $attributes;
    }
}
