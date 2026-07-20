<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Http\Requests;

use Polymorph\Platform\Http\Requests\AuthenticatedRequest;
use Polymorph\Platform\Http\Pagination\V2\Concerns\HasPageRules;
use Polymorph\Platform\Http\Pagination\V2\Concerns\ResolvesPageRequest;

final class ListRecordsRequest extends AuthenticatedRequest
{
    use HasPageRules;
    use ResolvesPageRequest;

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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