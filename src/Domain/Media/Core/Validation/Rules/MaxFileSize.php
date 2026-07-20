<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Правило валидации максимального размера загружаемого файла.
 *
 * Проверяет, что размер файла не превышает указанный лимит в байтах.
 * Применяется к загружаемым файлам (UploadedFile).
 *
 * @package Polymorph\Platform\Domain\Media\Http\Rules
 */
class MaxFileSize implements ValidationRule
{
    /**
     * Создать новый экземпляр правила.
     *
     * @param int $maxSizeBytes Максимальный размер файла в байтах
     */
    public function __construct(
        private readonly int $maxSizeBytes
    ) {}

    /**
     * Выполнить валидацию файла.
     *
     * Проверяет:
     * - Значение является экземпляром UploadedFile
     * - Файл загружен корректно (isValid)
     * - Размер файла не превышает лимит
     *
     * @param string $attribute Имя атрибута
     * @param mixed $value Проверяемое значение
     * @param Closure $fail Callback для регистрации ошибки
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Проверка типа значения
        if (!$value instanceof UploadedFile) {
            return; // Пропускаем, если не файл (должно обрабатываться правилом 'file')
        }

        // Проверка загрузки файла
        if (!$value->isValid()) {
            return; // Пропускаем, если файл не загружен корректно
        }

        $fileSizeBytes = $value->getSize();

        // Проверка размера файла
        if ($fileSizeBytes > $this->maxSizeBytes) {
            $maxSizeMb = round($this->maxSizeBytes / (1024 * 1024), 2);
            $fileSizeMb = round($fileSizeBytes / (1024 * 1024), 2);
            
            $fail("Размер файла ({$fileSizeMb} MB) превышает максимально допустимый размер ({$maxSizeMb} MB).");
        }
    }
}
