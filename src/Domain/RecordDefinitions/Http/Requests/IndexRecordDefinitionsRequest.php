<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Polymorph\Platform\Http\Pagination\V2\Concerns\HasPageRules;
use Polymorph\Platform\Http\Pagination\V2\Concerns\ResolvesPageRequest;

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