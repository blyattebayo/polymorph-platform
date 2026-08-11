<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для выхода из системы.
 *
 * Валидирует опциональный параметр 'all' для отзыва всех сессий.
 */
final class LogoutRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Аутентификация выполняется route middleware и use case.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - all: опциональный boolean для отзыва всех сессий (по умолчанию отзывается только текущая)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'all' => ['sometimes', 'boolean'],
        ];
    }
}
