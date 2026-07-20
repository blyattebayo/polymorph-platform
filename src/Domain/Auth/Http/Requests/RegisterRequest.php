<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Polymorph\Platform\Support\Validation\ValidationRules;

/**
 * Request для публичной самостоятельной регистрации.
 *
 * Зеркалит правила LoginRequest по email/password и добавляет проверку
 * уникальности email и опциональное имя. Ролей не назначаем — базовый
 * аутентифицированный доступ роли не требует.
 */
final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [...ValidationRules::email(), Rule::unique('users', 'email')],
            'password' => ValidationRules::password(),
        ];
    }
}
