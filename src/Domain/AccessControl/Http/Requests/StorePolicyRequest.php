<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Polymorph\Platform\Support\Validation\ValidationRules;

final class StorePolicyRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'resource_pattern' => ['required', 'string', 'max:500'],
            'action' => ValidationRules::aclAction(),
            'effect' => ['required', 'string', 'in:allow,deny'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
