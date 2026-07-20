<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexAdminPersonalAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['active', 'revoked', 'expired'])],
        ];
    }

    /**
     * @return array{user_id?: int|null, status?: string|null}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return array_filter([
            'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            'status' => isset($validated['status']) ? (string) $validated['status'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
