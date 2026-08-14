<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\SdkBridge;

use Polymorph\Sdk\Data\Entity;
use Polymorph\Sdk\Data\ExtensionData;
use Polymorph\Sdk\Data\Repository;

/** Extension-scoped, memoized implementation of the public SDK data contract. */
final class ScopedExtensionData implements ExtensionData
{
    /** @var array<string, Repository<Entity>> */
    private array $repositories = [];

    public function __construct(
        private readonly ExtensionDataGateway $platform,
        private readonly string $extensionId,
    ) {}

    public function repository(string $entity, string $entityClass = Entity::class): Repository
    {
        return $this->repositories[$entity.'|'.$entityClass]
            ??= $this->platform->repository($this->extensionId, $entity, $entityClass);
    }
}
