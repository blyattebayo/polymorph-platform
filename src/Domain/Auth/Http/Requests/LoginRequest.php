<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Requests;

use Polymorph\Platform\Support\Validation\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для входа в систему (аутентификации).
 *
 * Валидирует email и password для входа администратора.
 *
 * @package Polymorph\Platform\Domain\Auth\Http\Requests
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ValidationRules::email(),
            'password' => ValidationRules::password(),
        ];
    }
}
