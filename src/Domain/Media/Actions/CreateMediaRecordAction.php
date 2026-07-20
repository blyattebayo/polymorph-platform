<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Actions;

use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\Models\MediaAvMetadata;
use Polymorph\Platform\Domain\Media\Core\Models\MediaImage;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;
use Polymorph\Platform\Domain\Media\Core\Dto\MediaMetadataDto;


/**
 * Action для создания Media записи и связанных метаданных.
 *
 * ВАЖНО: Этот Action НЕ создает транзакцию, он выполняется внутри
 * существующей транзакции, созданной в UploadMediaCommand.
 * Это предотвращает вложенные транзакции (nested transactions).
 *
 * Гарантирует, что либо ВСЕ записи созданы (Media + MediaImage/MediaAvMetadata),
 * либо НИЧЕГО не создано (при откате внешней транзакции).
 *
 * @package Polymorph\Platform\Domain\Media\Actions
 */
final readonly class CreateMediaRecordAction
{
    /**
     * Создать Media запись и связанные метаданные.
     *
     * Выполняется внутри транзакции! Не создает собственную транзакцию.
     *
     * @param string $diskName Имя диска хранения
     * @param string $path Путь к файлу на диске
     * @param string $originalName Оригинальное имя файла
     * @param string $extension Расширение файла
     * @param string $mime MIME-тип файла
     * @param int $sizeBytes Размер файла в байтах
     * @param string $checksum SHA256 checksum файла
     * @param MediaKind $kind Тип медиа
     * @param MediaMetadataDto $metadata Метаданные файла
     * @param array<string, mixed> $payload Дополнительные данные (title, alt)
     * @return Media Созданная запись с загруженными связями
     * @throws \Throwable При ошибке создания (откатывается внешней транзакцией)
     */
    public function execute(
        string $diskName,
        string $path,
        string $originalName,
        string $extension,
        string $mime,
        int $sizeBytes,
        string $checksum,
        MediaKind $kind,
        MediaMetadataDto $metadata,
        array $payload
    ): Media {
        // 1. Создать основную запись Media
        // Используем forceFill для ВСЕХ полей, т.к. это Action (trusted context)
        // Mass assignment protection работает на уровне Request validation
        $media = new Media();
        $media->forceFill([
            'disk' => $diskName,
            'path' => $path,
            'original_name' => $originalName,
            'ext' => $extension,
            'mime' => $mime,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => $checksum,
            'title' => $payload['title'] ?? null,
            'alt' => $payload['alt'] ?? null,
        ]);
        
        $media->save();

        // 2. Для изображений: создать MediaImage если есть размеры
        // Валидация размеров выполнена на уровне Request
        if ($kind === MediaKind::Image && $metadata->width !== null && $metadata->height !== null) {
            MediaImage::create([
                'media_id' => $media->id,
                'width' => $metadata->width,
                'height' => $metadata->height,
            ]);
        }

        // 3. Для видео/аудио: создать MediaAvMetadata
        if ($kind === MediaKind::Video || $kind === MediaKind::Audio) {
            $normalized = [
                'duration_ms' => $metadata->durationMs,
                'bitrate_kbps' => $metadata->bitrateKbps,
                'frame_rate' => $metadata->frameRate,
                'frame_count' => $metadata->frameCount,
                'video_codec' => $metadata->videoCodec,
                'audio_codec' => $metadata->audioCodec,
            ];

            // Создаём запись только если есть хотя бы одно поле с данными
            $hasData = array_reduce(
                $normalized,
                fn (bool $carry, $value): bool => $carry || $value !== null,
                false
            );

            if ($hasData) {
                MediaAvMetadata::create(array_merge(
                    ['media_id' => $media->id],
                    $normalized
                ));
            }
        }

        // 4. Загрузить связи для возврата полного объекта
        $media->load(['image', 'avMetadata']);

        return $media;
    }
}
