<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Contracts;

use Polymorph\Platform\Domain\Media\Core\Collections\MediaCollection;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaQuery;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Репозиторий для работы с медиа-файлами
 */
interface MediaRepository
{
    /**
     * Найти медиа по ID
     */
    public function find(string $id): ?Media;

    /**
     * Найти медиа по ID или выбросить исключение
     *
     * @throws \Polymorph\Platform\Domain\Media\Core\Exceptions\MediaNotFoundException
     */
    public function findOrFail(string $id): Media;

    /**
     * Найти медиа по ID, включая мягко удалённые (без eager-load связей).
     */
    public function findWithTrashed(string $id): ?Media;

    /**
     * Найти медиа по ID для отображения: включая мягко удалённые,
     * с предзагруженными связями (image, avMetadata).
     */
    public function findForDisplay(string $id): ?Media;

    /**
     * Найти медиа по checksum
     */
    public function findByChecksum(string $checksum): ?Media;

    /**
     * Найти медиа по пути в хранилище
     */
    public function findByPath(string $disk, string $path): ?Media;

    /**
     * Получить все медиа (без пагинации)
     */
    public function all(): MediaCollection;

    /**
     * Создать новый медиа-файл
     */
    public function create(array $data): Media;

    /**
     * Обновить медиа-файл
     */
    public function update(Media $media, array $data): Media;

    /**
     * Обновить медиа по ID (включая мягко удаленные)
     *
     * @param string $id ULID медиа
     * @param array<string, mixed> $attributes Поля для обновления (title, alt)
     * @return Media
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateById(string $id, array $attributes): Media;

    /**
     * Мягкое удаление медиа
     */
    public function delete(Media $media): void;

    /**
     * Полное удаление медиа из БД
     */
    public function forceDelete(Media $media): void;

    /**
     * Восстановить удаленный медиа
     */
    public function restore(Media $media): void;

    /**
     * Поиск с фильтрацией и пагинацией
     */
    public function search(MediaQuery $query): LengthAwarePaginator;

    /**
     * Проверить существование медиа с checksum
     */
    public function existsByChecksum(string $checksum): bool;

    /**
     * Получить медиа по массиву ID
     *
     * @param array<int, string> $ids
     */
    public function findMany(array $ids): MediaCollection;

    /**
     * Получить только мягко удалённые медиа по массиву ID.
     *
     * @param array<int, string> $ids
     */
    public function findManyTrashed(array $ids): MediaCollection;

    /**
     * Получить медиа по массиву ID, включая мягко удалённые.
     *
     * @param array<int, string> $ids
     */
    public function findManyWithTrashed(array $ids): MediaCollection;

    /**
     * Получить все мягко удалённые медиа (корзина).
     */
    public function allTrashed(): MediaCollection;

    /**
     * Мягкое удаление медиа по массиву ID
     *
     * @param array<int, string> $ids
     * @return int Количество удаленных записей
     */
    public function deleteMany(array $ids): int;

    /**
     * Полное удаление медиа по массиву ID
     *
     * @param array<int, string> $ids
     * @return int Количество удаленных записей
     */
    public function forceDeleteMany(array $ids): int;

    /**
     * Восстановить медиа по массиву ID
     *
     * @param array<int, string> $ids
     * @return int Количество восстановленных записей
     */
    public function restoreMany(array $ids): int;
}
