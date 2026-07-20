<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Http\Requests;

use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Http\Requests\ApiFormRequest;

/**
 * Request для обновления медиа-файла.
 *
 * Валидирует данные для обновления метаданных медиа-файла:
 * - Все поля опциональны
 * - title: опциональный заголовок (минимум 1, максимум 255 символов, если указан)
 * - alt: опциональный alt текст (минимум 1, максимум 255 символов, если указан)
 *
 * @package Polymorph\Platform\Http\Requests\Admin\Media
 */
class UpdateMediaRequest extends ApiFormRequest
{
    /**
     * Подготовить данные для валидации.
     *
     * Нормализует пустые строки в null для title и alt, чтобы они не проходили валидацию min:1.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Нормализация title: пустые строки → null
        if ($this->has('title') && is_string($this->input('title'))) {
            $title = trim($this->input('title'));
            $this->merge(['title' => $title !== '' ? $title : null]);
        }

        // Нормализация alt: пустые строки → null
        if ($this->has('alt') && is_string($this->input('alt'))) {
            $alt = trim($this->input('alt'));
            $this->merge(['alt' => $alt !== '' ? $alt : null]);
        }
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует (все поля опциональны):
     * - title: заголовок (минимум 1, максимум 255 символов, если указан)
     * - alt: alt текст (минимум 1, максимум 255 символов, если указан)
     *
     * Запрещенные поля (protected from mass assignment):
     * - id, disk, path, checksum_sha256, mime, size_bytes, original_name, ext
     * - created_at, updated_at, deleted_at
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'nullable|filled|string|min:1|max:255',
            'alt' => 'nullable|filled|string|min:1|max:255',
            
            // Запретить технические поля (security protection)
            'id' => 'prohibited',
            'disk' => 'prohibited',
            'path' => 'prohibited',
            'checksum_sha256' => 'prohibited',
            'mime' => 'prohibited',
            'size_bytes' => 'prohibited',
            'original_name' => 'prohibited',
            'ext' => 'prohibited',
            
            // Запретить системные timestamp поля
            'created_at' => 'prohibited',
            'updated_at' => 'prohibited',
            'deleted_at' => 'prohibited',
        ];
    }

    protected function validationErrorDetail(): string
    {
        return 'The media update payload failed validation constraints.';
    }
}
