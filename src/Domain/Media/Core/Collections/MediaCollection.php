<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Collections;

use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;
use Illuminate\Database\Eloquent\Collection;

/**
 * Типизированная коллекция медиа-файлов
 *
 * @extends Collection<int, Media>
 */
class MediaCollection extends Collection
{
    /**
     * Фильтровать по типу медиа
     */
    public function filterByKind(MediaKind $kind): self
    {
        return $this->filter(fn (Media $media) => $media->kind() === $kind);
    }

    /**
     * Только изображения
     */
    public function onlyImages(): self
    {
        return $this->filterByKind(MediaKind::Image);
    }

    /**
     * Только видео
     */
    public function onlyVideos(): self
    {
        return $this->filterByKind(MediaKind::Video);
    }

    /**
     * Только аудио
     */
    public function onlyAudio(): self
    {
        return $this->filterByKind(MediaKind::Audio);
    }

    /**
     * Только документы
     */
    public function onlyDocuments(): self
    {
        return $this->filterByKind(MediaKind::Document);
    }

    /**
     * Общий размер всех файлов в байтах
     */
    public function totalSize(): int
    {
        return $this->sum(fn (Media $media) => $media->size_bytes);
    }

    /**
     * Общий размер в мегабайтах
     */
    public function totalSizeInMB(): float
    {
        return round($this->totalSize() / (1024 * 1024), 2);
    }

    /**
     * Группировать по MIME типу
     *
     * @return \Illuminate\Support\Collection<string, static>
     */
    public function groupByMime(): \Illuminate\Support\Collection
    {
        return $this->groupBy(fn (Media $media) => $media->mime_type)
            ->map(fn ($items) => new static($items));
    }

    /**
     * Группировать по типу медиа (kind)
     *
     * @return \Illuminate\Support\Collection<string, static>
     */
    public function groupByKind(): \Illuminate\Support\Collection
    {
        return $this->groupBy(fn (Media $media) => $media->kind()->value)
            ->map(fn ($items) => new static($items));
    }

    /**
     * Найти медиа по checksum
     */
    public function findByChecksum(string $checksum): ?Media
    {
        return $this->first(fn (Media $media) => $media->checksum_sha256 === $checksum);
    }

    /**
     * Только медиа с вариантами
     */
    public function withVariants(): self
    {
        return $this->filter(fn (Media $media) => $media->variants()->exists());
    }

    /**
     * Только медиа без вариантов
     */
    public function withoutVariants(): self
    {
        return $this->filter(fn (Media $media) => !$media->variants()->exists());
    }

    /**
     * Фильтровать по расширению файла
     */
    public function filterByExtension(string $extension): self
    {
        $extension = strtolower(trim($extension, '.'));
        return $this->filter(fn (Media $media) => strtolower($media->extension) === $extension);
    }

    /**
     * Медиа созданные после указанной даты
     */
    public function createdAfter(\DateTimeInterface $date): self
    {
        return $this->filter(fn (Media $media) => $media->created_at > $date);
    }

    /**
     * Медиа созданные до указанной даты
     */
    public function createdBefore(\DateTimeInterface $date): self
    {
        return $this->filter(fn (Media $media) => $media->created_at < $date);
    }

    /**
     * Сортировать по размеру (по возрастанию)
     */
    public function sortBySize(bool $descending = false): self
    {
        return $descending
            ? $this->sortByDesc(fn (Media $media) => $media->size_bytes)
            : $this->sortBy(fn (Media $media) => $media->size_bytes);
    }

    /**
     * Сортировать по дате создания
     */
    public function sortByCreatedAt(bool $descending = true): self
    {
        return $descending
            ? $this->sortByDesc(fn (Media $media) => $media->created_at)
            : $this->sortBy(fn (Media $media) => $media->created_at);
    }
}
