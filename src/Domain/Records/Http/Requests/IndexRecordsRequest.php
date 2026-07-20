<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Polymorph\Platform\Http\Pagination\V2\Concerns\HasPageRules;
use Polymorph\Platform\Http\Pagination\V2\Concerns\ResolvesPageRequest;
use Polymorph\Platform\Http\Requests\AuthenticatedRequest;

final class IndexRecordsRequest extends AuthenticatedRequest
{
    use HasPageRules;
    use ResolvesPageRequest;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->pageRules(), [
            'record_definition_id' => 'required|integer|exists:record_definitions,id',
        ]);
    }

    public function messages(): array
    {
        return [
            'record_definition_id.required' => 'record_definition_id is required for records list.',
            'record_definition_id.exists' => 'The specified record definition does not exist.',
        ];
    }
}
