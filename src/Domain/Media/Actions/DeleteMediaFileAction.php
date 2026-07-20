<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Actions;

use Illuminate\Support\Facades\Storage;

/**
 * Action для удаления медиа-файла с диска.
 */
final readonly class DeleteMediaFileAction
{
    /**
     * Удалить файл с диска.
     *
     * @param  string  $disk  Имя диска
     * @param  string  $path  Путь к файлу
     * @return bool True если файл удален или не существовал
     */
    public function execute(string $disk, string $path): bool
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return true;
        }

        return $storage->delete($path);
    }
}
