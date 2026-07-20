<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Http\Requests;

use Polymorph\Platform\Domain\Users\Http\Requests\Rules\AssignableRoleIdsRule;
use Polymorph\Platform\Domain\Users\Http\Requests\Rules\ValidUserStatusRule;
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
            'email' => ValidationRules::email(),
            'password' => ValidationRules::password(),
            'status' => ['nullable', new ValidUserStatusRule],
            'role_ids' => ['nullable', 'array', app(AssignableRoleIdsRule::class)],
            'role_ids.*' => ['integer', 'min:1'],
        ];
    }
}
