<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Http\Requests;

use Illuminate\Validation\Rule;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Http\Requests\Rules\AssignableRoleIdsRule;
use Polymorph\Platform\Domain\Users\Http\Requests\Rules\ValidUserStatusRule;
use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Polymorph\Platform\Support\Validation\ValidationRules;

final class UpdateUserRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Свой же email занятым не считается — иначе сохранение формы без
            // правки почты падало бы на самом пользователе.
            'email' => [
                ...ValidationRules::email(required: false),
                Rule::unique(User::class, 'email')->ignore((int) $this->route('userId')),
            ],
            'status' => ['sometimes', new ValidUserStatusRule],
            'role_ids' => ['sometimes', 'array', app(AssignableRoleIdsRule::class)],
            'role_ids.*' => ['integer', 'min:1'],
        ];
    }
}
