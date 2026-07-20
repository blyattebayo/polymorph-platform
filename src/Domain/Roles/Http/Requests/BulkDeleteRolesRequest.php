<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;

final class BulkDeleteRolesRequest extends ApiFormRequest
{
    /**
     * @return array<string,array<int,string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ids = $this->input('ids', []);
            if (! is_array($ids)) {
                return;
            }

            if (count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
                $validator->errors()->add('ids', 'Duplicate role ids are not allowed.');
            }
        });
    }
}
