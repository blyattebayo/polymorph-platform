<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Commands;

use Polymorph\Platform\Domain\Media\Actions\CalculateChecksumAction;
use Polymorph\Platform\Domain\Media\Actions\CreateMediaRecordAction;
use Polymorph\Platform\Domain\Media\Actions\ExtractMetadataAction;
use Polymorph\Platform\Domain\Media\Actions\FindDuplicateMediaAction;
use Polymorph\Platform\Domain\Media\Actions\StoreMediaFileAction;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;
use Polymorph\Platform\Domain\Media\Events\MediaUploaded;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Command для загрузки медиа-файла.
 *
 * Оркестрирует все шаги загрузки, гарантируя атомарность операций:
 * 1. Вычисление checksum
 * 2. Проверка дедупликации (с pessimistic lock)
 * 3. Извлечение метаданных
 * 4. Атомарное сохранение файла + создание записей в БД
 * 5. Dispatch событий ПОСЛЕ успешного коммита
 *
 * Если любой шаг падает - предыдущие откатываются.
 * Это решает проблему 1.2.1: отсутствие транзакций при загрузке.
 *
 * @package Polymorph\Platform\Domain\Media\Commands
 */
final readonly class UploadMediaCommand
{
    public function __construct(
        private CalculateChecksumAction $calculateChecksum,
        private FindDuplicateMediaAction $findDuplicate,
        private StoreMediaFileAction $storeFile,
        private ExtractMetadataAction $extractMetadata,
        private CreateMediaRecordAction $createRecord,
    ) {
    }

    /**
     * Выполнить загрузку медиа-файла.
     *
     * @param UploadedFile $file Уже провалидированный файл (через StoreMediaRequest)
     * @param array<string, mixed> $payload Дополнительные данные (title, alt)
     * @return Media Созданная или существующая запись
     * @throws \InvalidArgumentException Если файл не валиден
     * @throws \RuntimeException При ошибках сохранения
     */
    public function execute(UploadedFile $file, array $payload = []): Media
    {
        // Валидация должна быть выполнена в Request
        if (!$file->isValid()) {
            throw new \InvalidArgumentException(
                'File must be validated before upload. Use StoreMediaRequest validation.'
            );
        }

        // ШАГ 1: Вычислить checksum (всегда возвращает string, не nullable)
        $checksum = $this->calculateChecksum->execute($file);

        // ШАГ 2: Проверка дедупликации с пессимистичным lock
        // Предотвращает race condition (проблема 1.2.2)
        $existing = $this->findDuplicate->execute($checksum, $payload);
        
        if ($existing !== null) {
            // Дубликат найден - возвращаем существующую запись
            // Файл НЕ загружается повторно
            return $existing;
        }

        // ШАГ 3: Определить тип медиа и извлечь только нужные метаданные
        $mime = $file->getMimeType() ?? $file->getClientMimeType() ?? 'application/octet-stream';
        $kind = MediaKind::fromMime($mime);
        $metadata = $this->extractMetadata->execute($file, $mime, $kind);

        // ШАГ 4-6: Сохранение файла + создание записей в единой транзакции
        try {
            $media = DB::transaction(function () use (
                $file,
                $checksum,
                $mime,
                $kind,
                $metadata,
                $payload
            ) {
                // 4. Сохранить файл на диск
                $storeResult = $this->storeFile->execute($file, $kind, $checksum);

                // 5. Создать все записи в БД атомарно
                try {
                    $media = $this->createRecord->execute(
                        diskName: $storeResult->disk,
                        path: $storeResult->path,
                        originalName: $file->getClientOriginalName() ?: $file->getFilename(),
                        extension: $storeResult->extension,
                        mime: $mime,
                        sizeBytes: (int) ($file->getSize() ?? 0),
                        checksum: $checksum,
                        kind: $kind,
                        metadata: $metadata,
                        payload: $payload
                    );

                    return $media;
                } catch (\Throwable $e) {
                    // Откатить сохранение файла при ошибке БД
                    $this->storeFile->rollback($storeResult->disk, $storeResult->path);

                    throw $e;
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            // Гонка дедупликации: параллельный аплоад с тем же checksum успел
            // создать запись между нашей проверкой и вставкой. Уникальный индекс
            // checksum_sha256 не дал создать дубль; файл уже откатан выше.
            // Возвращаем существующую запись — для клиента это тот же дедуп.
            $existing = $this->findDuplicate->execute($checksum, $payload);

            if ($existing !== null) {
                return $existing;
            }

            throw $e;
        }

        // ШАГ 7: Отправить события ПОСЛЕ успешного коммита транзакции
        // Это гарантирует, что события не диспатчатся при rollback
        Event::dispatch(new MediaUploaded($media));

        return $media;
    }
}
