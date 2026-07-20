<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Http\Requests;

use Polymorph\Platform\Http\Pagination\V2\Concerns\HasPageRules;
use Polymorph\Platform\Http\Pagination\V2\Concerns\ResolvesPageRequest;
use Polymorph\Platform\Http\Requests\ApiFormRequest;

final class IndexRecordDefinitionsRequest extends ApiFormRequest
{
    use HasPageRules;
    use ResolvesPageRequest;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->pageRules();
    }
}
