<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Listeners;

use Polymorph\Platform\Domain\Media\Events\MediaDeleted;
use Polymorph\Platform\Domain\Media\Events\MediaForceDeleted;
use Polymorph\Platform\Domain\Media\Events\MediaProcessed;
use Polymorph\Platform\Domain\Media\Events\MediaRestored;
use Polymorph\Platform\Domain\Media\Events\MediaUpdated;
use Polymorph\Platform\Domain\Media\Events\MediaUploaded;
use Polymorph\Platform\Domain\Media\Events\VariantGenerated;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Слушатель для логирования событий медиа-файлов.
 *
 * Логирует все события жизненного цикла медиа-файлов:
 * - загрузка (MediaUploaded)
 * - обновление метаданных (MediaUpdated)
 * - восстановление из корзины (MediaRestored)
 * - обработка вариантов (MediaProcessed / VariantGenerated)
 * - удаление (MediaDeleted)
 * - окончательное удаление (MediaForceDeleted)
 */
final class LogMediaEvent
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    public function handleMediaUploaded(MediaUploaded $event): void
    {
        $media = $event->media;

        $this->logger->event('media.file_uploaded', [
            'media_id' => $media->id,
            'original_name' => $media->original_name,
            'mime' => $media->mime,
            'size_bytes' => $media->size_bytes,
            'disk' => $media->disk,
            'path' => $media->path,
        ]);
    }

    public function handleMediaUpdated(MediaUpdated $event): void
    {
        $media = $event->media;

        $this->logger->event('media.file_updated', [
            'media_id' => $media->id,
            'original_name' => $media->original_name,
            'mime' => $media->mime,
        ]);
    }

    public function handleMediaRestored(MediaRestored $event): void
    {
        $media = $event->media;

        $this->logger->event('media.file_restored', [
            'media_id' => $media->id,
            'original_name' => $media->original_name,
            'mime' => $media->mime,
        ]);
    }

    public function handleMediaProcessed(MediaProcessed $event): void
    {
        $media = $event->media;
        $variant = $event->variant;

        $this->logger->event('media.variant.processed', [
            'media_id' => $media->id,
            'variant' => $variant->variant,
            'variant_path' => $variant->path,
            'variant_size_bytes' => $variant->size_bytes,
            'variant_width' => $variant->width,
            'variant_height' => $variant->height,
        ]);
    }

    public function handleVariantGenerated(VariantGenerated $event): void
    {
        $variant = $event->variant;

        $this->logger->event('media.variant.generated', [
            'media_id' => $variant->media_id,
            'variant' => $variant->variant,
            'variant_width' => $variant->width,
            'variant_height' => $variant->height,
        ]);
    }

    public function handleMediaDeleted(MediaDeleted $event): void
    {
        $media = $event->media;

        $this->logger->event('media.file_deleted', [
            'media_id' => $media->id,
            'original_name' => $media->original_name,
            'mime' => $media->mime,
            'size_bytes' => $media->size_bytes,
            'disk' => $media->disk,
            'path' => $media->path,
        ]);
    }

    public function handleMediaForceDeleted(MediaForceDeleted $event): void
    {
        $media = $event->media;

        $this->logger->event('media.file_force_deleted', [
            'media_id' => $media->id,
            'original_name' => $media->original_name,
            'mime' => $media->mime,
            'size_bytes' => $media->size_bytes,
            'checksum' => $media->checksum_sha256,
            'disk' => $media->disk,
            'path' => $media->path,
        ]);
    }
}
