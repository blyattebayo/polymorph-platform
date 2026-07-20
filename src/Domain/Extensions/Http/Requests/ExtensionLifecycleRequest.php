<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Polymorph\Platform\Support\Validation\ValidationRules;

final class ExtensionLifecycleRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'plugin_id' => ValidationRules::slug(),
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
