<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;

final class BulkDeleteSchemasRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ];
    }
}
