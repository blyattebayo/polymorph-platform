<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для сохранения/обновления JSON-конфига формы (PUT).
 *
 * Минимальная валидация:
 * - config_json: обязательный JSON (после декодирования — массив PHP)
 */
class StoreEntryViewRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Авторизация обрабатывается middleware маршрута.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует только наличие и JSON-тип config_json.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'config_json' => [
                'required',
                'array',
            ],
        ];
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function messages(): array
    {
        return [
            'config_json.required' => 'The config_json field is required.',
            'config_json.array' => 'The config_json field must be a valid JSON object or array.',
        ];
    }
}
