<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Структурная валидация дерева меню. Семантика узлов (типы ссылок, URL и т.п.)
 * — ответственность фронта; здесь только форма дерева и ограничение глубины.
 */
final class SaveMenuRequest extends FormRequest
{
    private const MAX_DEPTH = 2;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tree' => ['present', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tree = $this->input('tree', []);
            if (is_array($tree)) {
                $this->validateDepth($validator, $tree, 1);
            }
        });
    }

    /**
     * Валидированное дерево.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(): array
    {
        /** @var list<array<string, mixed>> $tree */
        $tree = $this->validated()['tree'] ?? [];

        return $tree;
    }

    /**
     * @param  array<int, mixed>  $nodes
     */
    private function validateDepth(Validator $validator, array $nodes, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            $validator->errors()->add('tree', 'Menu nesting exceeds maximum depth of '.self::MAX_DEPTH.'.');

            return;
        }

        foreach ($nodes as $index => $node) {
            if (! is_array($node)) {
                $validator->errors()->add("tree.{$index}", 'Menu item must be an object.');

                continue;
            }

            $children = $node['children'] ?? null;
            if ($children !== null && ! is_array($children)) {
                $validator->errors()->add("tree.{$index}.children", 'Children must be an array.');

                continue;
            }

            if (is_array($children) && $children !== []) {
                $this->validateDepth($validator, $children, $depth + 1);
            }
        }
    }
}
