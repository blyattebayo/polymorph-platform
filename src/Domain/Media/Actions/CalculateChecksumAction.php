<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Actions;

use Illuminate\Http\UploadedFile;
use Polymorph\Platform\Domain\Media\Core\Exceptions\MediaProcessingException;

/**
 * Action для вычисления SHA256 checksum файла.
 *
 * Использует hash_file() для эффективного вычисления checksum.
 * Функция hash_file() работает с потоком данных внутри, поэтому эффективна
 * по памяти даже для больших файлов.
 *
 * ВАЖНО: Не использует random/ULID fallback для сохранения корректной дедупликации.
 * Если файл не может быть прочитан - выбрасывается исключение.
 */
final readonly class CalculateChecksumAction
{
    /**
     * Вычислить SHA256 checksum файла.
     *
     * @param  UploadedFile  $file  Загруженный файл
     * @return string SHA256 hex (64 символа)
     *
     * @throws MediaProcessingException Если файл не может быть прочитан
     */
    public function execute(UploadedFile $file): string
    {
        // Получаем путь к файлу с fallback
        // getRealPath() быстрее, но getPathname() более надёжная
        $path = $file->getRealPath() ?: $file->getPathname();

        if (! $path || ! is_readable($path)) {
            throw MediaProcessingException::metadataExtraction(
                'Unable to calculate file checksum: file is not readable or accessible. '.
                'File: '.($file->getClientOriginalName() ?? 'unknown').', '.
                'Size: '.($file->getSize() ?? 0).' bytes'
            );
        }

        // hash_file() эффективна по памяти (работает с потоком внутри)
        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw MediaProcessingException::metadataExtraction(
                'Failed to calculate checksum for file: '.
                ($file->getClientOriginalName() ?? 'unknown')
            );
        }

        return $hash;
    }
}
