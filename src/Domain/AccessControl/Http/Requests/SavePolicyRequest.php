<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Polymorph\Platform\Support\Validation\ValidationRules;

final class SavePolicyRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'resource_pattern' => ['required', 'string', 'max:500'],
            'action' => ValidationRules::aclAction(),
            'effect' => ['required', 'string', 'in:allow,deny'],
        ];
    }
}
