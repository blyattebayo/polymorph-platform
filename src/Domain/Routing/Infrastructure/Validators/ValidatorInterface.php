<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Validators;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Интерфейс для валидаторов правил RouteNode по kind.
 *
 * Определяет контракт для построения правил валидации
 * для различных типов узлов маршрутов (group, route).
 */
interface ValidatorInterface
{
    /**
     * Построить правила валидации для создания RouteNode (StoreRouteNodeRequest).
     *
     * Правила должны быть специфичны для поддерживаемого kind.
     * Общие правила (kind, parent_id, sort_order, enabled, readonly) обрабатываются отдельно.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function buildRulesForStore(): array;

    /**
     * Построить правила валидации для обновления RouteNode (UpdateRouteNodeRequest).
     *
     * Правила должны быть специфичны для поддерживаемого kind.
     * Общие правила обрабатываются отдельно.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function buildRulesForUpdate(): array;

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function buildMessages(): array;
}
