<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;

final class SyncExtensionsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [];
    }
}
