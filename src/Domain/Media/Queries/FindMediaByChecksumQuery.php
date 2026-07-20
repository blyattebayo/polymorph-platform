<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Queries;

use Polymorph\Platform\Domain\Media\Core\Contracts\MediaRepository;
use Polymorph\Platform\Domain\Media\Core\Models\Media;

/**
 * Query для поиска медиа по checksum.
 * 
 * Часть CQRS паттерна - операция только чтения.
 *
 * @package Polymorph\Platform\Domain\Media\Queries
 */
final readonly class FindMediaByChecksumQuery
{
    public function __construct(
        private MediaRepository $repository
    ) {
    }

    /**
     * Найти медиа по checksum.
     *
     * @param string $checksum SHA256 checksum
     * @return Media|null Найденная запись или null
     */
    public function execute(string $checksum): ?Media
    {
        return $this->repository->findByChecksum($checksum);
    }

    /**
     * Проверить существование медиа по checksum.
     *
     * @param string $checksum SHA256 checksum
     * @return bool True если медиа существует
     */
    public function exists(string $checksum): bool
    {
        return $this->repository->existsByChecksum($checksum);
    }
}
