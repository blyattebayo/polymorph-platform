<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Http\Requests;

use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * Request для массового мягкого удаления медиа-файлов.
 *
 * Валидирует массив идентификаторов медиа-файлов для массового мягкого удаления.
 *
 * @package Polymorph\Platform\Http\Requests\Admin\Media
 */
class BulkDeleteMediaRequest extends ApiFormRequest
{
    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - ids: обязательный массив идентификаторов медиа-файлов (минимум 1 элемент, максимум 100)
     * - ids.*: каждый идентификатор должен быть строкой (ULID)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'string', 'size:26'],
        ];
    }

    /**
     * Получить кастомные сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'The ids field is required.',
            'ids.array' => 'The ids must be an array.',
            'ids.min' => 'At least one media ID is required.',
            'ids.max' => 'Maximum 100 media IDs allowed per request.',
            'ids.*.required' => 'Each media ID is required.',
            'ids.*.string' => 'Each media ID must be a string.',
            'ids.*.size' => 'Each media ID must be exactly 26 characters (ULID).',
        ];
    }

    protected function validationErrorDetail(): string
    {
        return 'The media bulk delete payload failed validation constraints.';
    }

    /**
     * Выполнить кастомную валидацию.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator Валидатор
     * @return void
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ids = $this->input('ids', []);
            if (! is_array($ids)) {
                return;
            }

            // Проверяем наличие дубликатов
            $uniqueIds = array_unique($ids);
            if (count($uniqueIds) !== count($ids)) {
                $validator->errors()->add(
                    'ids',
                    'Duplicate media IDs are not allowed.'
                );
            }
        });
    }
}

