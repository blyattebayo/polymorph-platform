<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Polymorph\Platform\Http\Pagination\V2\Concerns\HasPageRules;
use Polymorph\Platform\Http\Pagination\V2\Concerns\ResolvesPageRequest;

final class IndexRolesRequest extends ApiFormRequest
{
    use HasPageRules;
    use ResolvesPageRequest;

    public function rules(): array
    {
        return $this->pageRules();
    }
}
