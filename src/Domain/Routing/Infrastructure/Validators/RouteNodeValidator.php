<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Validators;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;

/**
 * Валидатор правил для узлов типа ROUTE.
 *
 * Строит правила валидации для узлов с kind='route'.
 * Маршруты могут иметь: uri, methods, name, domain, middleware, where, defaults, action_meta, action_type.
 * Маршруты НЕ могут иметь: prefix, namespace, children.
 */
final class RouteNodeValidator implements ValidatorInterface
{
    /**
     * Список валидных HTTP методов для маршрутов.
     *
     * @var array<string>
     */
    private const VALID_HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    /**
     * Список валидных HTTP статусов для редиректов.
     *
     * @var array<int>
     */
    private const VALID_REDIRECT_STATUSES = [301, 302, 307, 308];

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
     * Правила для route-узлов.
     *
     * Store и update отличаются только «головой» правила:
     * - $req — поле обязательно (при update можно не присылать, но не обнулять);
     * - $opt — поле необязательно и обнуляемо.
     *
     * Условные правила action_meta.* в обеих версиях одинаковы.
     *
     * Поля prefix/namespace/children правил не имеют — Laravel не вернёт их
     * из validate(), то есть до модели они не доедут.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function buildRules(bool $isUpdate): array
    {
        // 'sometimes','required' вместо 'sometimes','nullable': обнулить uri или
        // methods у существующего маршрута означало бесшумно погасить его —
        // регистратор пропускает такой узел, и URI молча уходил в 404.
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];
        $opt = $isUpdate ? ['sometimes', 'nullable'] : ['nullable'];

        return [
            'uri' => [...$req, 'string', 'max:255'],
            'methods' => [...$req, 'array', 'min:1'],
            'methods.*' => [Rule::in(self::VALID_HTTP_METHODS)],
            'name' => [...$opt, 'string', 'max:255'],
            'domain' => [...$opt, 'string', 'max:255'],
            'middleware' => [...$opt, 'array'],
            'middleware.*' => ['string'],
            'where' => [...$opt, 'array'],
            'defaults' => [...$opt, 'array'],
            'action_type' => [...$req, Rule::in(RouteNodeActionType::values())],
            'action_meta' => [...$req, 'array'],
            // для CONTROLLER
            'action_meta.action' => [
                'required_if:action_type,controller',
                'prohibited_unless:action_type,controller',
                'string',
                'max:512',
            ],
            // для VIEW
            'action_meta.view' => [
                'required_if:action_type,view',
                'prohibited_unless:action_type,view',
                'string',
                'max:512',
            ],
            'action_meta.data' => [
                'nullable',
                'prohibited_unless:action_type,view',
                'array',
            ],
            // для REDIRECT
            'action_meta.to' => [
                'required_if:action_type,redirect',
                'prohibited_unless:action_type,redirect',
                'string',
                'max:255',
            ],
            'action_meta.status' => [
                'nullable',
                'prohibited_unless:action_type,redirect',
                'integer',
                Rule::in(self::VALID_REDIRECT_STATUSES),
            ],
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
            'uri.required' => 'Поле uri обязательно для узлов типа route.',
            'uri.string' => 'Поле uri должно быть строкой.',
            'uri.max' => 'Поле uri не может быть длиннее 255 символов.',
            'methods.required' => 'Поле methods обязательно для узлов типа route.',
            'methods.array' => 'Поле methods должно быть массивом.',
            'methods.min' => 'Поле methods должно содержать хотя бы один метод.',
            'methods.*.in' => 'HTTP метод должен быть одним из: '.implode(', ', self::VALID_HTTP_METHODS).'.',
            'name.string' => 'Поле name должно быть строкой.',
            'name.max' => 'Поле name не может быть длиннее 255 символов.',
            'domain.string' => 'Поле domain должно быть строкой.',
            'domain.max' => 'Поле domain не может быть длиннее 255 символов.',
            'middleware.array' => 'Поле middleware должно быть массивом.',
            'middleware.*.string' => 'Все элементы в middleware должны быть строками.',
            'where.array' => 'Поле where должно быть массивом.',
            'defaults.array' => 'Поле defaults должно быть массивом.',
            'action_type.required' => 'Поле action_type обязательно для узлов типа route.',
            'action_type.in' => 'Поле action_type должно быть одним из: '.implode(', ', RouteNodeActionType::values()).'.',
            'action_meta.required' => 'Поле action_meta обязательно для узлов типа route.',
            'action_meta.array' => 'Поле action_meta должно быть массивом.',
            'action_meta.action.required_if' => 'Поле action_meta.action обязательно для action_type=controller.',
            'action_meta.action.string' => 'Поле action_meta.action должно быть строкой.',
            'action_meta.action.max' => 'Поле action_meta.action не может быть длиннее 512 символов.',
            'action_meta.view.required_if' => 'Поле action_meta.view обязательно для action_type=view.',
            'action_meta.view.string' => 'Поле action_meta.view должно быть строкой.',
            'action_meta.view.max' => 'Поле action_meta.view не может быть длиннее 512 символов.',
            'action_meta.data.array' => 'Поле action_meta.data должно быть массивом.',
            'action_meta.to.required_if' => 'Поле action_meta.to обязательно для action_type=redirect.',
            'action_meta.to.string' => 'Поле action_meta.to должно быть строкой.',
            'action_meta.to.max' => 'Поле action_meta.to не может быть длиннее 255 символов.',
            'action_meta.status.integer' => 'Поле action_meta.status должно быть целым числом.',
            'action_meta.status.in' => 'Поле action_meta.status должно быть одним из: '.implode(', ', self::VALID_REDIRECT_STATUSES).'.',
        ];
    }
}
