<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Queries;

use Polymorph\Platform\Domain\Media\Core\Contracts\MediaRepository;
use Polymorph\Platform\Domain\Media\Core\Exceptions\MediaNotFoundException;
use Polymorph\Platform\Domain\Media\Core\Models\Media;

/**
 * Query для поиска медиа по ID.
 * 
 * Часть CQRS паттерна - операция только чтения.
 *
 * @package Polymorph\Platform\Domain\Media\Queries
 */
final readonly class FindMediaByIdQuery
{
    public function __construct(
        private MediaRepository $repository
    ) {
    }

    /**
     * Найти медиа по ID.
     *
     * @param string $id ULID медиа
     * @return Media|null Найденная запись или null
     */
    public function execute(string $id): ?Media
    {
        return $this->repository->find($id);
    }

    /**
     * Найти медиа по ID или выбросить исключение.
     *
     * @param string $id ULID медиа
     * @return Media Найденная запись
     * @throws MediaNotFoundException Если медиа не найдено
     */
    public function executeOrFail(string $id): Media
    {
        return $this->repository->findOrFail($id);
    }
}
