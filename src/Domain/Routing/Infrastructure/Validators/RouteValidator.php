<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Валидатор для динамических маршрутов.
 *
 * Предоставляет методы для получения правил валидации
 * для различных типов узлов маршрутов через систему валидаторов.
 *
 * @package Polymorph\Platform\Domain\Routing\Validators
 */
final class RouteValidator
{
    public function __construct(
        private ValidatorRegistry $registry,
    ) {}
    /**
     * Валидировать данные узла маршрута.
     *
     * @param array<string, mixed> $data Данные для валидации
     * @param bool $isUpdate true для обновления, false для создания
     * @return array<string, mixed> Валидированные данные
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(array $data, bool $isUpdate = false): array
    {
        $kind = $data['kind'] ?? null;
        if (!is_string($kind)) {
            throw ValidationException::withMessages([
                'kind' => ['Поле kind обязательно и должно быть строкой.'],
            ]);
        }
        
        // Получаем специфичные правила для типа узла
        $dataValidator = $this->registry->getValidator($kind);
        $specificRules = $isUpdate
            ? $dataValidator->buildRulesForUpdate()
            : $dataValidator->buildRulesForStore();
        
        // Добавляем общие правила для всех узлов
        $commonRules = $this->buildCommonRules($isUpdate);
        $rules = array_merge($commonRules, $specificRules);
        
        $messages = $dataValidator->buildMessages();
        $validator = Validator::make($data, $rules, $messages);
        return $validator->validate();
    }
    
    /**
     * Построить общие правила валидации для всех типов узлов.
     *
     * @param bool $isUpdate true для обновления, false для создания
     * @return array<string, array<mixed>>
     */
    private function buildCommonRules(bool $isUpdate): array
    {
        if ($isUpdate) {
            return [
                'kind' => ['sometimes', 'required', 'string'],
                'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:route_nodes,id'],
                'enabled' => ['sometimes', 'required', 'boolean'],
                'sort_order' => ['sometimes', 'required', 'integer'],
                'owner' => ['sometimes', 'required', 'string', 'max:255'],
                'readonly' => ['sometimes', 'required', 'boolean'],
            ];
        }
        
        return [
            'kind' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:route_nodes,id'],
            'enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
            'owner' => ['sometimes', 'string', 'max:255'],
            'readonly' => ['sometimes', 'boolean'],
        ];
    }
}
