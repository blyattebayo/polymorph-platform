<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Http\Resources\Media;

use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Media\Core\Models\Media;

/**
 * API Resource для аудио (Media).
 *
 * Возвращает специфичные поля для аудио:
 * duration_ms, bitrate_kbps, audio_codec.
 *
 * Требует загруженную связь `avMetadata` для получения AV-метаданных.
 * Все AV-поля опциональны (могут быть null).
 * Не включает видео-специфичные поля (frame_rate, frame_count, video_codec).
 */
class MediaAudioResource extends BaseMediaResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Включает базовые поля и специфичные для аудио:
     * duration_ms, bitrate_kbps, audio_codec.
     *
     * @param  Request  $request  HTTP запрос
     * @return array<string, mixed> Массив с полями аудио
     */
    public function toArray($request): array
    {
        /** @var Media $media */
        $media = $this->resource;

        // Загружаем связь, если не загружена
        if (! $media->relationLoaded('avMetadata')) {
            $media->load('avMetadata');
        }

        $avMetadata = $media->avMetadata;

        $base = parent::toArray($request);

        return array_merge($base, [
            'duration_ms' => $avMetadata?->duration_ms ? (int) $avMetadata->duration_ms : null,
            'bitrate_kbps' => $avMetadata?->bitrate_kbps ? (int) $avMetadata->bitrate_kbps : null,
            'audio_codec' => $avMetadata?->audio_codec,
        ]);
    }
}
