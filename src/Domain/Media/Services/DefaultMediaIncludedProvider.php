<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Services;

use Polymorph\Platform\Domain\Media\Core\Contracts\MediaIncludedProvider;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;

final class DefaultMediaIncludedProvider implements MediaIncludedProvider
{
    /**
     * @param string[] $mediaIds
     */
    public function buildIncludedByIds(array $mediaIds): \stdClass
    {
        if ($mediaIds === []) {
            return new \stdClass();
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Media> $mediaItems */
        $mediaItems = Media::query()
            ->whereIn('id', $mediaIds)
            ->whereNull('deleted_at')
            ->with(['image', 'avMetadata'])
            ->get();

        $result = new \stdClass();
        foreach ($mediaItems as $media) {
            $result->{(string) $media->id} = $this->mapMediaIncluded($media);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMediaIncluded(Media $media): array
    {
        $payload = [
            'id' => $media->id,
            'kind' => $media->kind()->value,
            'name' => $media->original_name,
            'ext' => $media->ext,
            'mime' => $media->mime,
            'size_bytes' => (int) $media->size_bytes,
            'title' => $media->title,
            'alt' => $media->alt,
            'created_at' => $media->created_at?->toIso8601String(),
            'updated_at' => $media->updated_at?->toIso8601String(),
            'deleted_at' => $media->deleted_at?->toIso8601String(),
            'url' => route('api.v1.media.show', ['id' => $media->id]),
        ];

        return match ($media->kind()) {
            MediaKind::Image => array_merge($payload, [
                'width' => $media->image?->width ? (int) $media->image->width : null,
                'height' => $media->image?->height ? (int) $media->image->height : null,
                'preview_urls' => $this->buildPreviewUrls($media),
            ]),
            MediaKind::Video => array_merge($payload, [
                'duration_ms' => $media->avMetadata?->duration_ms ? (int) $media->avMetadata->duration_ms : null,
                'bitrate_kbps' => $media->avMetadata?->bitrate_kbps ? (int) $media->avMetadata->bitrate_kbps : null,
                'frame_rate' => $media->avMetadata?->frame_rate ? (float) $media->avMetadata->frame_rate : null,
                'frame_count' => $media->avMetadata?->frame_count ? (int) $media->avMetadata->frame_count : null,
                'video_codec' => $media->avMetadata?->video_codec,
                'audio_codec' => $media->avMetadata?->audio_codec,
            ]),
            MediaKind::Audio => array_merge($payload, [
                'duration_ms' => $media->avMetadata?->duration_ms ? (int) $media->avMetadata->duration_ms : null,
                'bitrate_kbps' => $media->avMetadata?->bitrate_kbps ? (int) $media->avMetadata->bitrate_kbps : null,
                'audio_codec' => $media->avMetadata?->audio_codec,
            ]),
            MediaKind::Document => $payload,
        };
    }

    /**
     * @return array<string, string>
     */
    private function buildPreviewUrls(Media $media): array
    {
        $urls = [];

        foreach (array_keys(config('media.variants', [])) as $variant) {
            $urls[$variant] = route('api.v1.media.show', [
                'id' => $media->id,
                'variant' => $variant,
            ]);
        }

        return $urls;
    }
}
