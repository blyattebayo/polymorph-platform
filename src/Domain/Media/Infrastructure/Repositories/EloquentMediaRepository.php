<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Polymorph\Platform\Domain\Media\Core\Collections\MediaCollection;
use Polymorph\Platform\Domain\Media\Core\Contracts\MediaRepository;
use Polymorph\Platform\Domain\Media\Core\Exceptions\MediaNotFoundException;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaDeletedFilter;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaQuery;

/**
 * Eloquent реализация репозитория медиа-файлов
 */
final class EloquentMediaRepository implements MediaRepository
{
    /**
     * Найти медиа по ID
     */
    public function find(string $id): ?Media
    {
        return Media::find($id);
    }

    /**
     * Найти медиа по ID или выбросить исключение
     */
    public function findOrFail(string $id): Media
    {
        $media = $this->find($id);

        if ($media === null) {
            throw MediaNotFoundException::byId($id);
        }

        return $media;
    }

    public function findWithTrashed(string $id): ?Media
    {
        return Media::withTrashed()->find($id);
    }

    public function findForDisplay(string $id): ?Media
    {
        return Media::withTrashed()->with(['image', 'avMetadata'])->find($id);
    }

    /**
     * Найти медиа по checksum
     */
    public function findByChecksum(string $checksum): ?Media
    {
        return Media::where('checksum_sha256', $checksum)->first();
    }

    /**
     * Найти медиа по пути в хранилище
     */
    public function findByPath(string $disk, string $path): ?Media
    {
        return Media::where('disk', $disk)
            ->where('path', $path)
            ->first();
    }

    /**
     * Получить все медиа (без пагинации)
     */
    public function all(): MediaCollection
    {
        /** @var MediaCollection $collection */
        $collection = Media::all();

        return $collection;
    }

    /**
     * Создать новый медиа-файл
     */
    public function create(array $data): Media
    {
        return Media::create($data);
    }

    /**
     * Обновить медиа-файл
     */
    public function update(Media $media, array $data): Media
    {
        $media->update($data);

        return $media->fresh();
    }

    /**
     * Обновить медиа по ID (включая мягко удаленные)
     */
    public function updateById(string $id, array $attributes): Media
    {
        $media = Media::withTrashed()->find($id);
        if (! $media) {
            throw new ModelNotFoundException("Media {$id} not found.");
        }

        $media->fill($attributes);
        $media->save();

        return $media->fresh(['image', 'avMetadata']);
    }

    /**
     * Мягкое удаление медиа
     */
    public function delete(Media $media): void
    {
        $media->delete();
    }

    /**
     * Полное удаление медиа из БД
     */
    public function forceDelete(Media $media): void
    {
        $media->forceDelete();
    }

    /**
     * Восстановить удаленный медиа
     */
    public function restore(Media $media): void
    {
        $media->restore();
    }

    /**
     * Поиск с фильтрацией и пагинацией
     */
    public function search(MediaQuery $query): LengthAwarePaginator
    {
        $builder = $this->buildQuery($query)->with(['image', 'avMetadata']);

        return $builder->paginate(
            perPage: $query->perPage(),
            page: $query->page()
        );
    }

    /**
     * Проверить существование медиа с checksum
     */
    public function existsByChecksum(string $checksum): bool
    {
        return Media::where('checksum_sha256', $checksum)->exists();
    }

    /**
     * Получить медиа по массиву ID
     */
    public function findMany(array $ids): MediaCollection
    {
        /** @var MediaCollection $collection */
        $collection = Media::whereIn('id', $ids)->get();

        return $collection;
    }

    public function findManyTrashed(array $ids): MediaCollection
    {
        /** @var MediaCollection $collection */
        $collection = Media::onlyTrashed()->whereIn('id', $ids)->get();

        return $collection;
    }

    public function findManyWithTrashed(array $ids): MediaCollection
    {
        /** @var MediaCollection $collection */
        $collection = Media::withTrashed()->whereIn('id', $ids)->get();

        return $collection;
    }

    public function allTrashed(): MediaCollection
    {
        /** @var MediaCollection $collection */
        $collection = Media::onlyTrashed()->get();

        return $collection;
    }

    /**
     * Мягкое удаление медиа по массиву ID
     */
    public function deleteMany(array $ids): int
    {
        return Media::whereIn('id', $ids)->delete();
    }

    /**
     * Полное удаление медиа по массиву ID
     */
    public function forceDeleteMany(array $ids): int
    {
        return Media::whereIn('id', $ids)->forceDelete() ?: 0;
    }

    /**
     * Восстановить медиа по массиву ID
     */
    public function restoreMany(array $ids): int
    {
        return Media::whereIn('id', $ids)->onlyTrashed()->restore();
    }

    /**
     * Построить запрос Eloquent по параметрам MediaQuery
     */
    private function buildQuery(MediaQuery $query): Builder
    {
        $builder = Media::query();

        // Фильтр по удаленным
        match ($query->deletedFilter()) {
            MediaDeletedFilter::WithDeleted => $builder->withTrashed(),
            MediaDeletedFilter::OnlyDeleted => $builder->onlyTrashed(),
            MediaDeletedFilter::DefaultOnlyNotDeleted => null,
        };

        // Поиск по тексту
        if ($query->search() !== null && $query->search() !== '') {
            $term = $query->search();
            $builder->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('original_name', 'like', "%{$term}%");
            });
        }

        // Фильтр по MIME префиксу
        if ($query->mimePrefix() !== null && $query->mimePrefix() !== '') {
            $builder->where('mime', 'like', $query->mimePrefix().'%');
        }

        if ($query->kind() !== null && $query->kind() !== '') {
            $builder->where('mime', 'like', $query->kind().'/%');
        }

        // Сортировка
        if ($query->sort() !== null) {
            $sortOrder = $query->order() ?? 'asc';
            $builder->orderBy($query->sort(), $sortOrder);
        } else {
            // Сортировка по умолчанию - по дате создания (новые сначала)
            $builder->orderBy('created_at', 'desc');
        }

        return $builder;
    }
}
