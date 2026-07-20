<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для fallback маршрута (404).
 *
 * Обрабатывает все запросы, которые не совпали с другими маршрутами.
 * Публичный запрос без валидации параметров.
 */
final class FallbackRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Публичный запрос, доступен всем.
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
