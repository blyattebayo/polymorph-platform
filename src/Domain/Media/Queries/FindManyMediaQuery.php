<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Queries;

use Polymorph\Platform\Domain\Media\Core\Collections\MediaCollection;
use Polymorph\Platform\Domain\Media\Core\Contracts\MediaRepository;

/**
 * Query для поиска нескольких медиа по массиву ID.
 * 
 * Часть CQRS паттерна - операция только чтения.
 *
 * @package Polymorph\Platform\Domain\Media\Queries
 */
final readonly class FindManyMediaQuery
{
    public function __construct(
        private MediaRepository $repository
    ) {
    }

    /**
     * Найти несколько медиа по массиву ID.
     *
     * @param array<string> $ids Массив ULID
     * @return MediaCollection Коллекция найденных медиа
     */
    public function execute(array $ids): MediaCollection
    {
        return $this->repository->findMany($ids);
    }
}
