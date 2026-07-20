<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Queries;

use Polymorph\Platform\Domain\Media\Core\Contracts\MediaRepository;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Query для поиска медиа с фильтрацией и пагинацией.
 * 
 * Часть CQRS паттерна - операция только чтения.
 *
 * @package Polymorph\Platform\Domain\Media\Queries
 */
final readonly class SearchMediaQuery
{
    public function __construct(
        private MediaRepository $repository
    ) {
    }

    /**
     * Поиск медиа с фильтрацией и пагинацией.
     *
     * @param MediaQuery $query Параметры поиска
     * @return LengthAwarePaginator Результаты с пагинацией
     */
    public function execute(MediaQuery $query): LengthAwarePaginator
    {
        return $this->repository->search($query);
    }
}
