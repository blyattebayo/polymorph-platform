<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Contracts;

use Polymorph\Platform\Domain\Media\Core\Collections\MediaVariantCollection;
use Polymorph\Platform\Domain\Media\Core\Exceptions\MediaNotFoundException;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\Models\MediaVariant;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaVariantStatus;

/**
 * Репозиторий для работы с вариантами медиа
 */
interface MediaVariantRepository
{
    /**
     * Найти вариант по ID
     */
    public function find(string $id): ?MediaVariant;

    /**
     * Найти вариант по ID или выбросить исключение
     *
     * @throws MediaNotFoundException
     */
    public function findOrFail(string $id): MediaVariant;

    /**
     * Найти вариант по имени для конкретного медиа
     */
    public function findByName(Media $media, string $variantName): ?MediaVariant;

    /**
     * Получить все варианты для медиа
     */
    public function getAllForMedia(Media $media): MediaVariantCollection;

    /**
     * Создать новый вариант
     */
    public function create(array $data): MediaVariant;

    /**
     * Обновить вариант
     */
    public function update(MediaVariant $variant, array $data): MediaVariant;

    /**
     * Удалить вариант
     */
    public function delete(MediaVariant $variant): void;

    /**
     * Удалить все варианты для медиа
     *
     * @return int Количество удаленных вариантов
     */
    public function deleteAllForMedia(Media $media): int;

    /**
     * Получить варианты по статусу
     */
    public function findByStatus(MediaVariantStatus $status, int $limit = 100): MediaVariantCollection;

    /**
     * Получить незавершенные варианты (queued или processing)
     */
    public function findUnfinished(int $limit = 100): MediaVariantCollection;

    /**
     * Получить варианты, застрявшие в обработке
     * (processing дольше указанного времени)
     */
    public function findStuckProcessing(int $minutes = 30): MediaVariantCollection;

    /**
     * Обновить статус варианта
     */
    public function updateStatus(MediaVariant $variant, MediaVariantStatus $status): MediaVariant;

    /**
     * Отметить вариант как готовый
     */
    public function markAsReady(MediaVariant $variant, array $data = []): MediaVariant;

    /**
     * Отметить вариант как неудачный
     */
    public function markAsFailed(MediaVariant $variant, string $errorMessage): MediaVariant;

    /**
     * Отметить вариант как обрабатываемый
     */
    public function markAsProcessing(MediaVariant $variant): MediaVariant;
}
