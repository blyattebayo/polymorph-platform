<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Валидатор для динамических маршрутов.
 *
 * Предоставляет методы для получения правил валидации
 * для различных типов узлов маршрутов через систему валидаторов.
 */
final class RouteValidator
{
    public function __construct(
        private ValidatorRegistry $registry,
    ) {}

    /**
     * Валидировать данные узла маршрута.
     *
     * @param  array<string, mixed>  $data  Данные для валидации
     * @param  bool  $isUpdate  true для обновления, false для создания
     * @return array<string, mixed> Валидированные данные
     *
     * @throws ValidationException
     */
    public function validate(array $data, bool $isUpdate = false): array
    {
        $kind = $data['kind'] ?? null;
        if (! is_string($kind)) {
            throw ValidationException::withMessages([
                'kind' => ['Поле kind обязательно и должно быть строкой.'],
            ]);
        }

        if (! $this->registry->hasValidator($kind)) {
            $supported = implode(', ', $this->registry->getSupportedKinds());
            throw ValidationException::withMessages([
                'kind' => ["Неизвестный kind «{$kind}». Поддерживаются: {$supported}."],
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
     * Store и update отличаются только «головой» правила, поэтому обе версии
     * строятся из одного описания: $req — обязательное поле, $opt — необязательное.
     *
     * Полей owner/readonly здесь намеренно нет: владение определяет ядро
     * (client для админ-CRUD, system/plugin проставляется загрузчиками через
     * RouteNodeDefinition::withOwnership), а не тело запроса. Раньше произвольная
     * строка в owner доходила до OwnerType::parse и роняла boot() целиком.
     *
     * @param  bool  $isUpdate  true для обновления, false для создания
     * @return array<string, array<mixed>>
     */
    private function buildCommonRules(bool $isUpdate): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];
        $opt = $isUpdate ? ['sometimes', 'required'] : ['sometimes'];

        return [
            'kind' => [...$req, 'string', Rule::in($this->registry->getSupportedKinds())],
            'parent_id' => [...($isUpdate ? ['sometimes'] : []), 'nullable', 'integer', 'exists:route_nodes,id'],
            'enabled' => [...$opt, 'boolean'],
            'sort_order' => [...$opt, 'integer'],
        ];
    }
}
