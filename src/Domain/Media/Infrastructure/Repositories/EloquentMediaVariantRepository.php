<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Infrastructure\Repositories;

use Polymorph\Platform\Domain\Media\Core\Collections\MediaVariantCollection;
use Polymorph\Platform\Domain\Media\Core\Contracts\MediaVariantRepository;
use Polymorph\Platform\Domain\Media\Core\Exceptions\MediaNotFoundException;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\Models\MediaVariant;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaVariantStatus;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent реализация репозитория вариантов медиа
 */
final class EloquentMediaVariantRepository implements MediaVariantRepository
{
    /**
     * Найти вариант по ID
     */
    public function find(string $id): ?MediaVariant
    {
        return MediaVariant::find($id);
    }

    /**
     * Найти вариант по ID или выбросить исключение
     */
    public function findOrFail(string $id): MediaVariant
    {
        $variant = $this->find($id);

        if ($variant === null) {
            throw MediaNotFoundException::byId($id);
        }

        return $variant;
    }

    /**
     * Найти вариант по имени для конкретного медиа
     */
    public function findByName(Media $media, string $variantName): ?MediaVariant
    {
        return MediaVariant::where('media_id', $media->id)
            ->where('variant', $variantName)
            ->first();
    }

    /**
     * Получить все варианты для медиа
     */
    public function getAllForMedia(Media $media): MediaVariantCollection
    {
        /** @var MediaVariantCollection $collection */
        $collection = MediaVariant::where('media_id', $media->id)->get();
        return $collection;
    }

    /**
     * Создать новый вариант
     */
    public function create(array $data): MediaVariant
    {
        return MediaVariant::create($data);
    }

    /**
     * Обновить вариант
     */
    public function update(MediaVariant $variant, array $data): MediaVariant
    {
        $variant->update($data);
        return $variant->fresh();
    }

    /**
     * Удалить вариант
     */
    public function delete(MediaVariant $variant): void
    {
        $variant->delete();
    }

    /**
     * Удалить все варианты для медиа
     */
    public function deleteAllForMedia(Media $media): int
    {
        return MediaVariant::where('media_id', $media->id)->delete();
    }

    /**
     * Получить варианты по статусу
     */
    public function findByStatus(MediaVariantStatus $status, int $limit = 100): MediaVariantCollection
    {
        /** @var MediaVariantCollection $collection */
        $collection = MediaVariant::where('status', $status)
            ->limit($limit)
            ->get();
        return $collection;
    }

    /**
     * Получить незавершенные варианты (queued или processing)
     */
    public function findUnfinished(int $limit = 100): MediaVariantCollection
    {
        /** @var MediaVariantCollection $collection */
        $collection = MediaVariant::whereIn('status', [
            MediaVariantStatus::Queued,
            MediaVariantStatus::Processing,
        ])
            ->limit($limit)
            ->get();
        return $collection;
    }

    /**
     * Получить варианты, застрявшие в обработке
     * (processing дольше указанного времени)
     */
    public function findStuckProcessing(int $minutes = 30): MediaVariantCollection
    {
        $threshold = now()->subMinutes($minutes);

        /** @var MediaVariantCollection $collection */
        $collection = MediaVariant::where('status', MediaVariantStatus::Processing)
            ->where('started_at', '<', $threshold)
            ->get();
        return $collection;
    }

    /**
     * Обновить статус варианта
     */
    public function updateStatus(MediaVariant $variant, MediaVariantStatus $status): MediaVariant
    {
        $variant->update(['status' => $status]);
        return $variant->fresh();
    }

    /**
     * Отметить вариант как готовый
     */
    public function markAsReady(MediaVariant $variant, array $data = []): MediaVariant
    {
        $variant->update([
            'status' => MediaVariantStatus::Ready,
            'finished_at' => now(),
            'error_message' => null,
            ...$data,
        ]);

        return $variant->fresh();
    }

    /**
     * Отметить вариант как неудачный
     */
    public function markAsFailed(MediaVariant $variant, string $errorMessage): MediaVariant
    {
        $variant->update([
            'status' => MediaVariantStatus::Failed,
            'finished_at' => now(),
            'error_message' => $errorMessage,
            'attempts' => DB::raw('attempts + 1'),
        ]);

        return $variant->fresh();
    }

    /**
     * Отметить вариант как обрабатываемый
     */
    public function markAsProcessing(MediaVariant $variant): MediaVariant
    {
        $variant->update([
            'status' => MediaVariantStatus::Processing,
            'started_at' => now(),
            'error_message' => null,
        ]);

        return $variant->fresh();
    }
}
