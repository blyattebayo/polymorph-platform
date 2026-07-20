<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Polymorph\Platform\Http\Pagination\V2\Concerns\HasPageRules;
use Polymorph\Platform\Http\Pagination\V2\Concerns\ResolvesPageRequest;
use Polymorph\Platform\Support\Validation\ValidationRules;

final class IndexPolicyRequest extends ApiFormRequest
{
    use HasPageRules;
    use ResolvesPageRequest;

    public function rules(): array
    {
        return array_merge($this->pageRules(), [
            'resource_pattern' => ['nullable', 'string', 'max:500'],
            'resource_prefix' => ['nullable', 'boolean'],
            'action' => ValidationRules::aclAction(required: false, nullable: true),
            'effect' => ['nullable', 'string', 'in:allow,deny'],
            'is_active' => ['nullable', 'boolean'],
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