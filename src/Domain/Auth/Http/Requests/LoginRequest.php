<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Polymorph\Platform\Support\Validation\ValidationRules;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ValidationRules::email(),
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
