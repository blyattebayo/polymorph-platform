<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\Media\Core\Exceptions\MediaStorageException;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;
use Polymorph\Platform\Domain\Media\Infrastructure\Services\StorageResolver;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * DTO для результата сохранения файла.
 */
final readonly class StoreMediaFileResult
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $extension,
    ) {}
}

/**
 * Action для сохранения медиа-файла на диск.
 *
 * Использует стратегию организации путей (by-date или hash-shard).
 * Генерирует уникальное имя файла на основе ULID.
 * Поддерживает rollback для отката сохранения при ошибках.
 */
final readonly class StoreMediaFileAction
{
    public function __construct(
        private StorageResolver $storageResolver,
        private AppLogger $logger,
    ) {}

    /**
     * Сохранить файл на диск.
     *
     * @param  UploadedFile  $file  Загруженный файл
     * @param  MediaKind  $kind  Тип медиа (для выбора диска)
     * @param  string|null  $checksum  SHA256 checksum (для hash-shard стратегии)
     * @return StoreMediaFileResult Результат с disk, path, extension
     *
     * @throws MediaStorageException Если не удалось сохранить файл
     */
    public function execute(
        UploadedFile $file,
        MediaKind $kind,
        ?string $checksum
    ): StoreMediaFileResult {
        // Resolve диск по типу медиа
        $diskName = $this->storageResolver->resolveDiskName($kind);
        $disk = Storage::disk($diskName);

        // Получить расширение файла (sanitize от path traversal)
        $originalName = $file->getClientOriginalName() ?: $file->getFilename();
        $extension = strtolower(
            $file->getClientOriginalExtension()
            ?: pathinfo($originalName, PATHINFO_EXTENSION)
            ?: $file->extension()
            ?: 'bin'
        );

        // Sanitize extension - только буквы и цифры
        $extension = preg_replace('/[^a-z0-9]/i', '', $extension);

        // Генерировать директорию по стратегии
        $strategy = config('media.path_strategy', 'by-date');
        $directory = match ($strategy) {
            'hash-shard' => $this->hashShardDirectory($checksum),
            default => $this->byDateDirectory(),
        };

        // Генерировать уникальное имя файла
        $baseName = strtolower((string) Str::ulid());
        $filename = $extension !== '' ? "{$baseName}.{$extension}" : $baseName;

        // Полный путь
        $path = trim($directory, '/');
        $fullPath = $path === '' ? $filename : "{$path}/{$filename}";

        // Сохранить файл
        $storedPath = $disk->putFileAs($path, $file, $filename);

        if (! $storedPath) {
            throw MediaStorageException::writeFailed(
                $diskName,
                $fullPath
            );
        }

        // Нормализовать путь (замена обратных слешей)
        $normalizedPath = str_replace('\\', '/', ltrim($storedPath, '/'));

        return new StoreMediaFileResult(
            disk: $diskName,
            path: $normalizedPath,
            extension: $extension,
        );
    }

    /**
     * Откатить сохранение файла (удалить).
     *
     * Используется при ошибке создания записи в БД.
     * НЕ бросает исключения - только логирует ошибки, чтобы не
     * затереть оригинальное исключение из execute().
     *
     * @param  string  $diskName  Имя диска
     * @param  string  $path  Путь к файлу
     * @return bool true если файл удален, false если не существовал или не удалось удалить
     */
    public function rollback(string $diskName, string $path): bool
    {
        try {
            $disk = Storage::disk($diskName);

            if ($disk->exists($path)) {
                return $disk->delete($path);
            }

            return false;
        } catch (\Throwable $e) {
            // Логируем, но НЕ бросаем исключение, чтобы не затереть
            // оригинальное исключение из execute() при rollback транзакции
            $this->logger->warning('media.store.rollback_delete_failed', [
                'disk' => $diskName,
                'path' => $path,
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Сформировать директорию по стратегии by-date.
     *
     * @return string Путь директории (YYYY/MM/DD)
     */
    private function byDateDirectory(): string
    {
        return now('UTC')->format('Y/m/d');
    }

    /**
     * Сформировать директорию по стратегии hash-shard.
     *
     * Использует первые 4 символа checksum для создания структуры XX/YY.
     * Если checksum недоступен - fallback на by-date.
     *
     * @param  string|null  $checksum  SHA256 checksum
     * @return string Путь директории
     */
    private function hashShardDirectory(?string $checksum): string
    {
        if ($checksum === null || strlen($checksum) < 4) {
            return $this->byDateDirectory();
        }

        return substr($checksum, 0, 2).'/'.substr($checksum, 2, 2);
    }
}
