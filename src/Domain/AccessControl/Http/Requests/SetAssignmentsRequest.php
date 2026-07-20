<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Requests;

use Polymorph\Platform\Domain\AccessControl\Http\Requests\Rules\ValidSubjectRule;
use Polymorph\Platform\Http\Requests\ApiFormRequest;

final class SetAssignmentsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255', new ValidSubjectRule()],
            'policy_ids' => ['present', 'array'],
            'policy_ids.*' => ['integer', 'min:1'],
        ];
    }
}