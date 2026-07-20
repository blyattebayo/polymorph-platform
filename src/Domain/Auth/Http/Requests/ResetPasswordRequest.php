<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Polymorph\Platform\Support\Validation\ValidationRules;

final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ValidationRules::email(),
            'token' => ['required', 'string'],
            'password' => ValidationRules::password(confirmed: true),
        ];
    }
}
