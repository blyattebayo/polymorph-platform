<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Collections;

use Illuminate\Database\Eloquent\Collection;
use Polymorph\Platform\Domain\Media\Core\Models\MediaVariant;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaVariantStatus;

/**
 * Типизированная коллекция вариантов медиа
 *
 * @extends Collection<int, MediaVariant>
 */
class MediaVariantCollection extends Collection
{
    /**
     * Найти вариант по имени
     */
    public function byVariant(string $name): ?MediaVariant
    {
        return $this->first(fn (MediaVariant $variant) => $variant->name === $name);
    }

    /**
     * Только готовые варианты
     */
    public function ready(): self
    {
        return $this->filter(fn (MediaVariant $variant) => $variant->status === MediaVariantStatus::Ready);
    }

    /**
     * Только неудачные варианты
     */
    public function failed(): self
    {
        return $this->filter(fn (MediaVariant $variant) => $variant->status === MediaVariantStatus::Failed);
    }

    /**
     * Только обрабатываемые варианты
     */
    public function processing(): self
    {
        return $this->filter(fn (MediaVariant $variant) => $variant->status === MediaVariantStatus::Processing);
    }

    /**
     * Только варианты в очереди
     */
    public function queued(): self
    {
        return $this->filter(fn (MediaVariant $variant) => $variant->status === MediaVariantStatus::Queued);
    }

    /**
     * Фильтровать по статусу
     */
    public function filterByStatus(MediaVariantStatus $status): self
    {
        return $this->filter(fn (MediaVariant $variant) => $variant->status === $status);
    }

    /**
     * Только завершенные варианты (готовые или неудачные)
     */
    public function finished(): self
    {
        return $this->filter(fn (MediaVariant $variant) => $variant->status->isFinished());
    }

    /**
     * Только незавершенные варианты (в очереди или обрабатываются)
     */
    public function unfinished(): self
    {
        return $this->reject(fn (MediaVariant $variant) => $variant->status->isFinished());
    }

    /**
     * Общий размер всех вариантов в байтах
     */
    public function totalSize(): int
    {
        return $this->sum(fn (MediaVariant $variant) => $variant->size_bytes ?? 0);
    }

    /**
     * Общий размер в мегабайтах
     */
    public function totalSizeInMB(): float
    {
        return round($this->totalSize() / (1024 * 1024), 2);
    }

    /**
     * Проверить, все ли варианты готовы
     */
    public function allReady(): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return $this->every(fn (MediaVariant $variant) => $variant->status === MediaVariantStatus::Ready);
    }

    /**
     * Проверить, есть ли хотя бы один неудачный вариант
     */
    public function hasFailures(): bool
    {
        return $this->contains(fn (MediaVariant $variant) => $variant->status === MediaVariantStatus::Failed);
    }

    /**
     * Процент готовых вариантов
     */
    public function completionPercentage(): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        $readyCount = $this->ready()->count();

        return round(($readyCount / $this->count()) * 100, 2);
    }

    /**
     * Получить имена всех вариантов
     *
     * @return array<int, string>
     */
    public function variantNames(): array
    {
        return $this->pluck('name')->unique()->values()->all();
    }

    /**
     * Сортировать по размеру файла
     */
    public function sortBySize(bool $descending = false): self
    {
        return $descending
            ? $this->sortByDesc(fn (MediaVariant $variant) => $variant->size_bytes ?? 0)
            : $this->sortBy(fn (MediaVariant $variant) => $variant->size_bytes ?? 0);
    }

    /**
     * Сортировать по дате создания
     */
    public function sortByCreatedAt(bool $descending = true): self
    {
        return $descending
            ? $this->sortByDesc(fn (MediaVariant $variant) => $variant->created_at)
            : $this->sortBy(fn (MediaVariant $variant) => $variant->created_at);
    }

    /**
     * Фильтровать по формату
     */
    public function filterByFormat(string $format): self
    {
        return $this->filter(fn (MediaVariant $variant) => $variant->format === $format);
    }

    /**
     * Варианты с ошибками (есть error_message)
     */
    public function withErrors(): self
    {
        return $this->filter(fn (MediaVariant $variant) => ! empty($variant->error_message));
    }
}
