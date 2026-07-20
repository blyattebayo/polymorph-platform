<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Http\Requests;

use Polymorph\Platform\Http\Requests\AuthenticatedRequest;

final class HydrateRecordsRequest extends AuthenticatedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'record_ids' => ['required', 'array', 'min:1', 'max:100'],
            'record_ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }
}
