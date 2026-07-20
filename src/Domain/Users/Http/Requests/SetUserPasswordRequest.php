<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Polymorph\Platform\Support\Validation\ValidationRules;

final class SetUserPasswordRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'password' => ValidationRules::password(),
        ];
    }
}