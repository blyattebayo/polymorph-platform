<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для переупорядочивания узлов маршрутов (RouteNode).
 *
 * Валидирует массив узлов для массового изменения parent_id и sort_order:
 * - nodes: обязательный массив узлов с id, parent_id, sort_order
 * - Все id должны существовать
 */
class ReorderRouteNodesRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Авторизация обрабатывается middleware маршрута.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - nodes: обязательный массив узлов
     * - nodes.*.id: обязательный ID узла (должен существовать)
     * - nodes.*.parent_id: опциональный ID родителя (должен существовать или быть null)
     * - nodes.*.sort_order: опциональный порядок сортировки (>= 0)
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nodes' => 'required|array|min:1',
            'nodes.*.id' => [
                'required',
                'integer',
                Rule::exists('route_nodes', 'id'),
            ],
            'nodes.*.parent_id' => [
                'nullable',
                'integer',
                Rule::exists('route_nodes', 'id'),
            ],
            'nodes.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function messages(): array
    {
        return [
            'nodes.required' => 'Поле nodes обязательно для заполнения.',
            'nodes.array' => 'Поле nodes должно быть массивом.',
            'nodes.min' => 'Поле nodes должно содержать хотя бы один элемент.',
            'nodes.*.id.required' => 'Каждый узел должен иметь id.',
            'nodes.*.id.exists' => 'Указанный узел не существует.',
            'nodes.*.parent_id.exists' => 'Указанный родительский узел не существует.',
            'nodes.*.sort_order.min' => 'Порядок сортировки не может быть отрицательным.',
        ];
    }
}
