<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Requests;

use Polymorph\Platform\Http\Pagination\V2\Concerns\HasPageRules;
use Polymorph\Platform\Http\Pagination\V2\Concerns\ResolvesPageRequest;
use Polymorph\Platform\Http\Requests\ApiFormRequest;

final class IndexPersonalAccessTokenRequest extends ApiFormRequest
{
    use HasPageRules;
    use ResolvesPageRequest;

    public function rules(): array
    {
        return $this->pageRules();
    }
}
