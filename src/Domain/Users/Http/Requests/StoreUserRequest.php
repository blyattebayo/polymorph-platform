<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Http\Requests;

use Illuminate\Validation\Rule;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Http\Requests\Rules\AssignableRoleIdsRule;
use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Polymorph\Platform\Support\Validation\ValidationRules;

final class StoreUserRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            // Занятый email — ошибка ПОЛЯ, а не конфликт ресурса: клиенту нужно
            // подсветить input, а не показывать общее уведомление. Домен всё
            // равно перепроверит (гонка двух запросов) и ответит 409.
            'email' => [...ValidationRules::email(), Rule::unique(User::class, 'email')],
            'password' => ValidationRules::password(),
            'status' => ['nullable', Rule::in(User::allowedStatuses())],
            'role_ids' => ['nullable', 'array', app(AssignableRoleIdsRule::class)],
            'role_ids.*' => ['integer', 'min:1'],
        ];
    }
}
