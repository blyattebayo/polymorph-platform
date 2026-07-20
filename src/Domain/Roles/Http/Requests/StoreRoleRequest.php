<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Polymorph\Platform\Support\Validation\ValidationRules;

final class StoreRoleRequest extends ApiFormRequest
{
    /**
     * @return array<string,array<int,string>>
     */
    public function rules(): array
    {
        return [
            'code' => [...ValidationRules::roleCode(), 'unique:roles,code'],
            'name' => ['required', 'string', 'max:191'],
        ];
    }
}
