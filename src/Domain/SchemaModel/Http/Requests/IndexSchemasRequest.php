<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Http\Requests;

use Polymorph\Platform\Http\Pagination\V2\Concerns\HasPageRules;
use Polymorph\Platform\Http\Pagination\V2\Concerns\ResolvesPageRequest;
use Polymorph\Platform\Http\Requests\ApiFormRequest;

final class IndexSchemasRequest extends ApiFormRequest
{
    use HasPageRules;
    use ResolvesPageRequest;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge($this->pageRules(), [
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'in:created_at,name,code,recordDefinitions_count'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
            'in_use' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->except(['page', 'per_page']);
    }
}
