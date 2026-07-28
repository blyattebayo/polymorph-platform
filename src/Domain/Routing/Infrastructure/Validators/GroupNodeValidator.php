<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Validators;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Валидатор правил для узлов типа GROUP.
 *
 * Строит правила валидации для узлов с kind='group'.
 * Группы маршрутов могут иметь: prefix, domain, namespace, middleware, where, children.
 * Группы НЕ могут иметь: uri, methods, name, action_type, action_meta.
 */
final class GroupNodeValidator implements ValidatorInterface
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function buildRulesForStore(): array
    {
        return $this->buildRules(isUpdate: false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function buildRulesForUpdate(): array
    {
        return $this->buildRules(isUpdate: true);
    }

    /**
     * Правила для group-узлов.
     *
     * Store и update отличались только префиксом 'sometimes' у каждого поля,
     * поэтому обе версии строятся из одного описания.
     *
     * Поля uri/methods/name/action_type/action_meta правил не имеют — Laravel
     * их просто не вернёт из validate(), то есть до модели они не доедут.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function buildRules(bool $isUpdate): array
    {
        $opt = $isUpdate ? ['sometimes', 'nullable'] : ['nullable'];

        return [
            'prefix' => [...$opt, 'string', 'max:255'],
            'domain' => [...$opt, 'string', 'max:255'],
            'namespace' => [...$opt, 'string', 'max:255'],
            'middleware' => [...$opt, 'array'],
            'middleware.*' => ['string'],
            'where' => [...$opt, 'array'],
            'children' => [...$opt, 'array'],
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
