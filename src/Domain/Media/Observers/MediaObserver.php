<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Observers;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Events\MediaDeleted;
use Polymorph\Platform\Domain\Media\Events\MediaForceDeleted;
use Polymorph\Platform\Domain\Media\Events\MediaRestored;
use Polymorph\Platform\Domain\Media\Events\MediaUpdated;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Observer для модели Media
 *
 * Обрабатывает события жизненного цикла Media и отправляет domain события.
 * Выделяет побочные эффекты из модели для лучшей тестируемости.
 */
class MediaObserver
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    /**
     * Обработка события перед созданием Media
     */
    public function creating(Media $media): void
    {
        // Логика перед созданием (если нужна)
    }

    /**
     * Обработка события после создания Media
     */
    public function created(Media $media): void
    {
        // MediaUploaded отправляется из UploadMediaCommand после завершения обработки
        $this->logger->event('media.created', [
            'media_id' => $media->id,
            'kind' => $media->kind()->value,
            'filename' => $media->original_name,
        ]);
    }

    /**
     * Обработка события перед обновлением Media
     */
    public function updating(Media $media): void
    {
        // Логика перед обновлением (если нужна)
    }

    /**
     * Обработка события после обновления Media
     */
    public function updated(Media $media): void
    {
        // Отправка domain события
        Event::dispatch(new MediaUpdated($media));

        $this->logger->event('media.updated', [
            'media_id' => $media->id,
            'changes' => $media->getChanges(),
        ]);
    }

    /**
     * Обработка события после soft delete Media
     */
    public function deleted(Media $media): void
    {
        // Отправка domain события
        /** @var Media $media */
        Event::dispatch(new MediaDeleted($media));

        $this->logger->event('media.soft_deleted', [
            'media_id' => $media->id,
        ]);
    }

    /**
     * Обработка события после force delete Media
     */
    public function forceDeleted(Media $media): void
    {
        // Отправка domain события
        Event::dispatch(new MediaForceDeleted($media));

        $this->logger->event('media.force_deleted', [
            'media_id' => $media->id,
            'checksum' => $media->checksum_sha256,
        ]);
    }

    /**
     * Обработка события после восстановления Media
     */
    public function restored(Media $media): void
    {
        // Отправка domain события
        Event::dispatch(new MediaRestored($media));

        $this->logger->event('media.restored', [
            'media_id' => $media->id,
        ]);
    }
}
