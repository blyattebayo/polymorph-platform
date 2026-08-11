<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IssuePersonalAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['required', 'date', 'after:now'],
            'scopes' => ['required', 'array', 'min:1', 'max:100'],
            'scopes.*' => ['required', 'array:resource,action'],
            'scopes.*.resource' => ['required', 'string', 'max:255'],
            'scopes.*.action' => ['required', 'string', 'max:100'],
        ];
    }
}
