<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Http\Resources;

use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;
use Polymorph\Platform\Domain\Media\Http\Resources\Media\BaseMediaResource;
use Polymorph\Platform\Domain\Media\Http\Resources\Media\MediaAudioResource;
use Polymorph\Platform\Domain\Media\Http\Resources\Media\MediaDocumentResource;
use Polymorph\Platform\Domain\Media\Http\Resources\Media\MediaImageResource;
use Polymorph\Platform\Domain\Media\Http\Resources\Media\MediaVideoResource;

/**
 * Фабрика для создания специализированных ресурсов медиа.
 *
 * Автоматически выбирает нужный ресурс в зависимости от типа медиа:
 * - MediaImageResource для изображений
 * - MediaVideoResource для видео
 * - MediaAudioResource для аудио
 * - MediaDocumentResource для документов
 *
 * Использование:
 * ```php
 * $resource = MediaResourceFactory::make($media);
 * return $resource;
 * ```
 *
 * @package Polymorph\Platform\Http\Resources
 */
class MediaResourceFactory
{
    /**
     * Создать специализированный ресурс для медиа-файла.
     *
     * Выбирает нужный ресурс на основе типа медиа (kind).
     *
     * @param \Polymorph\Platform\Domain\Media\Core\Models\Media $media Медиа-файл
     * @return \Polymorph\Platform\Domain\Media\Http\Resources\Media\BaseMediaResource Специализированный ресурс
     */
    public static function make(Media $media): BaseMediaResource
    {
        return match ($media->kind()) {
            MediaKind::Image => new MediaImageResource($media),
            MediaKind::Video => new MediaVideoResource($media),
            MediaKind::Audio => new MediaAudioResource($media),
            MediaKind::Document => new MediaDocumentResource($media),
        };
    }
}


