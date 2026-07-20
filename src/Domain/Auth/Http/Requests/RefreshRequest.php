<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для ротации refresh токена.
 *
 * Не требует параметров, refresh токен извлекается из HttpOnly cookie.
 */
final class RefreshRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Требует валидного refresh токена в cookie.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Получить правила валидации для запроса.
     *
     * @return array<string, mixed> Пустой массив (валидация не требуется)
     */
    public function rules(): array
    {
        return [];
    }
}
