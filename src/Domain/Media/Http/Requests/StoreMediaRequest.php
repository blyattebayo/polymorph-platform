<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Http\Requests;

use Polymorph\Platform\Domain\Media\Core\Validation\Rules\MaxFileSize;
use Polymorph\Platform\Domain\Media\Core\Validation\Rules\ValidMimeSignature;
use Polymorph\Platform\Http\Requests\ApiFormRequest;

/**
 * Request для загрузки медиа-файла.
 *
 * Валидирует данные для загрузки медиа-файла:
 * - file: обязательный файл (размер и MIME тип из конфига)
 * - title: опциональный заголовок (минимум 1, максимум 255 символов)
 * - alt: опциональный alt текст (минимум 1, максимум 255 символов)
 *
 * @package Polymorph\Platform\Http\Requests\Admin\Media
 */
class StoreMediaRequest extends ApiFormRequest
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
     * Валидирует:
     * - file: обязательный файл с проверками:
     *   - MIME-тип по сигнатурам (ValidMimeSignature)
     *   - Размер файла (MaxFileSize)
     *   - Размеры изображения (встроенное правило dimensions)
     * - title: опциональный заголовок (минимум 1, максимум 255 символов, если указан)
     * - alt: опциональный alt текст (минимум 1, максимум 255 символов, если указан)
     *
     * Запрещенные поля (protected from mass assignment):
     * - id, disk, path, checksum_sha256, mime, size_bytes, original_name, ext
     * - created_at, updated_at, deleted_at
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxFileSizeBytes = (int) config('media.max_upload_mb', 1024) * 1024 * 1024;
        $maxDimension = config('media.validation.dimensions.max');
        
        return [
            'file' => [
                'required',
                'file',
                new ValidMimeSignature(config('media.allowed_mimes')),
                new MaxFileSize($maxFileSizeBytes),
                "dimensions:max_width={$maxDimension},max_height={$maxDimension}",
            ],
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
        return 'The media payload failed validation constraints.';
    }
}
