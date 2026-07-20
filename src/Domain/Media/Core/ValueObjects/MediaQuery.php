<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\ValueObjects;

use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

/**
 * Value Object для параметров выборки медиа.
 */
final readonly class MediaQuery
{
    public function __construct(
        private ?string $search,
        private ?string $kind,
        private ?string $mimePrefix,
        private MediaDeletedFilter $deletedFilter,
        private string $sort,
        private string $order,
        private int $page,
        private int $perPage,
    ) {
    }

    /**
     * Текст поискового запроса (по title и original_name).
     */
    public function search(): ?string
    {
        return $this->search;
    }

    /**
     * Тип медиа: image|video|audio|document.
     */
    public function kind(): ?string
    {
        return $this->kind;
    }

    /**
     * Префикс MIME для фильтрации (например, image/).
     */
    public function mimePrefix(): ?string
    {
        return $this->mimePrefix;
    }

    /**
     * Правила учёта soft-deleted записей.
     */
    public function deletedFilter(): MediaDeletedFilter
    {
        return $this->deletedFilter;
    }

    /**
     * Поле сортировки.
     */
    public function sort(): string
    {
        return $this->sort;
    }

    /**
     * Направление сортировки: asc|desc.
     */
    public function order(): string
    {
        return $this->order;
    }

    /**
     * Номер страницы.
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Размер страницы (1-100).
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    public static function fromValidated(array $validated, PageRequest $page): self
    {
        $sort = (string) ($validated['sort'] ?? 'created_at');
        $order = strtolower((string) ($validated['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return new self(
            search: isset($validated['q']) ? (string) $validated['q'] : null,
            kind: isset($validated['kind']) ? (string) $validated['kind'] : null,
            mimePrefix: isset($validated['mime']) ? (string) $validated['mime'] : null,
            deletedFilter: MediaDeletedFilter::fromString(isset($validated['deleted']) ? (string) $validated['deleted'] : null),
            sort: $sort,
            order: $order,
            page: $page->page,
            perPage: $page->perPage,
        );
    }
}
