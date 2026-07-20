<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Polymorph\Platform\Domain\Media\Core\Contracts\MediaRepository;
use Polymorph\Platform\Domain\Media\Core\Models\Media;

/**
 * Command для обновления метаданных медиа.
 *
 * Обновляет только title и alt.
 * Технические поля (disk, path, checksum) не изменяются.
 * Поддерживает обновление мягко удаленных записей.
 *
 * Часть CQRS паттерна - операция изменения состояния.
 */
final readonly class UpdateMediaCommand
{
    public function __construct(
        private MediaRepository $repository
    ) {}

    /**
     * Обновить метаданные медиа по ID.
     * Работает в том числе с soft-deleted записями.
     *
     * @param  string  $id  ULID медиа
     * @param  array<string, mixed>  $attributes  Атрибуты для обновления (title, alt)
     * @return Media Обновленная запись с загруженными отношениями
     *
     * @throws ModelNotFoundException
     */
    public function execute(string $id, array $attributes): Media
    {
        // Разрешаем обновлять только метаданные
        $allowed = array_intersect_key($attributes, array_flip(['title', 'alt']));

        if (empty($allowed)) {
            // Если нет разрешенных атрибутов, возвращаем текущую запись
            $media = Media::withTrashed()->findOrFail($id);

            return $media->load(['image', 'avMetadata']);
        }

        return $this->repository->updateById($id, $allowed);
    }
}
