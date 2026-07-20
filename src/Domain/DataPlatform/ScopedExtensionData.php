<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform;

use Polymorph\Sdk\Data\Entity;
use Polymorph\Sdk\Data\ExtensionData;
use Polymorph\Sdk\Data\Repository;

/**
 * Хост-реализация {@see ExtensionData}: скоупнутая на расширение фабрика
 * репозиториев поверх единого шва {@see DataPlatform}, с мемоизацией по
 * (entity, entityClass).
 */
final class ScopedExtensionData implements ExtensionData
{
    /** @var array<string, Repository<Entity>> */
    private array $repositories = [];

    public function __construct(
        private readonly DataPlatform $platform,
        private readonly string $extensionId,
    ) {
    }

    public function repository(string $entity, string $entityClass = Entity::class): Repository
    {
        return $this->repositories[$entity . '|' . $entityClass]
            ??= $this->platform->repository($this->extensionId, $entity, $entityClass);
    }
}
