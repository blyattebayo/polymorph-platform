<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Requests;

use Polymorph\Platform\Support\Validation\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
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
        ];
    }
}
