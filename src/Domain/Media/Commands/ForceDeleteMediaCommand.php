<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Commands;

use Polymorph\Platform\Domain\Media\Actions\DeleteMediaFileAction;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Illuminate\Support\Facades\DB;

/**
 * Command для полного удаления медиа (force delete).
 *
 * Удаляет:
 * - Запись из БД
 * - Файл с диска
 * - Все варианты (MediaVariant) и их файлы
 * - Связанные метаданные (cascade)
 *
 * Часть CQRS паттерна - операция изменения состояния.
 */
final readonly class ForceDeleteMediaCommand
{
    public function __construct(
        private DeleteMediaFileAction $deleteFile,
        private AppLogger $logger,
    ) {}

    /**
     * Полностью удалить медиа.
     *
     * @param  Media  $media  Медиа для удаления
     */
    public function execute(Media $media): void
    {
        DB::transaction(function () use ($media) {
            // 1. Удалить все варианты и их файлы
            foreach ($media->variants as $variant) {
                try {
                    $this->deleteFile->execute($variant->disk ?? $media->disk, $variant->path);
                } catch (\Throwable $e) {
                    // Логируем но продолжаем
                    $this->logger->warning('media.force_delete.variant_file_delete_failed', [
                        'variant_id' => $variant->id,
                        'path' => $variant->path,
                        'exception' => $e,
                    ]);
                }
            }

            // 2. Удалить основной файл
            try {
                $this->deleteFile->execute($media->disk, $media->path);
            } catch (\Throwable $e) {
                $this->logger->warning('media.force_delete.file_delete_failed', [
                    'media_id' => $media->id,
                    'path' => $media->path,
                    'exception' => $e,
                ]);
            }

            // 3. Force delete из БД (cascade удалит image, avMetadata, variants)
            $media->forceDelete();
        });
    }
}
