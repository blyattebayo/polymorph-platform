<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Actions;

use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;
use Polymorph\Platform\Domain\Media\Core\Dto\MediaMetadataDto;
use Polymorph\Platform\Domain\Media\Services\Metadata\MediaMetadataExtractor;
use Illuminate\Http\UploadedFile;

/**
 * Action для извлечения метаданных из медиа-файла.
 *
 * Извлекает метаданные через MediaMetadataExtractor.
 *
 * @package Polymorph\Platform\Domain\Media\Actions
 */
final readonly class ExtractMetadataAction
{
    public function __construct(
        private MediaMetadataExtractor $metadataExtractor,
    ) {
    }

    /**
     * Извлечь метаданные из файла.
     *
     * @param UploadedFile $file Загруженный файл
     * @param string $mime MIME-тип файла
     * @param MediaKind $kind Тип медиа (для условного извлечения)
     * @return MediaMetadataDto Метаданные файла
     */
    public function execute(UploadedFile $file, string $mime, MediaKind $kind): MediaMetadataDto
    {
        // Извлечь метаданные через extractor (условно, в зависимости от типа)
        return $this->metadataExtractor->extract($file, $mime, $kind);
    }
}
