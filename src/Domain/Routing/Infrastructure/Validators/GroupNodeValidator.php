<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Validators;

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;

/**
 * Валидатор правил для узлов типа GROUP.
 *
 * Строит правила валидации для узлов с kind='group'.
 * Группы маршрутов могут иметь: prefix, domain, namespace, middleware, where, children.
 * Группы НЕ могут иметь: uri, methods, name, action_type, action_meta.
 *
 * @package Polymorph\Platform\Domain\Routing\Validators
 */
final class GroupNodeValidator implements ValidatorInterface
{
    /**
     * Построить правила валидации для group-узлов при создании.
     *
     * Правила для StoreRouteNodeRequest:
     * - prefix: nullable, string, max:255
     * - domain: nullable, string, max:255
     * - namespace: nullable, string, max:255
     * - middleware: nullable, array
     * - where: nullable, array
     * - children: nullable, array (для будущей поддержки)
    * - Запретить: uri, methods, name, action_type, action_meta
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForStore(): array
    {
        return [
            'prefix' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'namespace' => ['nullable', 'string', 'max:255'],
            'middleware' => ['nullable', 'array'],
            'middleware.*' => ['string'],
            'where' => ['nullable', 'array'],
            'children' => ['nullable', 'array'], // Для будущей поддержки
        ];
    }

    /**
     * Построить правила валидации для group-узлов при обновлении.
     *
     * Правила для UpdateRouteNodeRequest (аналогично Store, но с sometimes):
     * - prefix: sometimes, nullable, string, max:255
     * - domain: sometimes, nullable, string, max:255
     * - namespace: sometimes, nullable, string, max:255
     * - middleware: sometimes, nullable, array
     * - where: sometimes, nullable, array
     * - children: sometimes, nullable, array
    * - Запретить: uri, methods, name, action_type, action_meta
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForUpdate(): array
    {
        return [
            'prefix' => ['sometimes', 'nullable', 'string', 'max:255'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'namespace' => ['sometimes', 'nullable', 'string', 'max:255'],
            'middleware' => ['sometimes', 'nullable', 'array'],
            'middleware.*' => ['string'],
            'where' => ['sometimes', 'nullable', 'array'],
            'children' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function buildMessages(): array
    {
        return [
            'prefix.string' => 'Поле prefix должно быть строкой.',
            'prefix.max' => 'Поле prefix не может быть длиннее 255 символов.',
            'domain.string' => 'Поле domain должно быть строкой.',
            'domain.max' => 'Поле domain не может быть длиннее 255 символов.',
            'namespace.string' => 'Поле namespace должно быть строкой.',
            'namespace.max' => 'Поле namespace не может быть длиннее 255 символов.',
            'middleware.array' => 'Поле middleware должно быть массивом.',
            'where.array' => 'Поле where должно быть массивом.',
            'children.array' => 'Поле children должно быть массивом.',
        ];
    }
}
