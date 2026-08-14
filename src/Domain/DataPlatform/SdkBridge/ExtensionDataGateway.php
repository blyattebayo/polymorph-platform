<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\SdkBridge;

use Polymorph\Sdk\Data\DefinitionRef;
use Polymorph\Sdk\Data\Entity;
use Polymorph\Sdk\Data\Repository;
use Polymorph\Sdk\Data\SchemaSpec;

/** Extension SDK gateway; it exposes no storage or maintenance authority. */
final class ExtensionDataGateway
{
    public function __construct(
        private readonly SdkRecordRepositoryFactory $repositories,
        private readonly ExtensionDefinitionProvisioner $definitions,
    ) {}

    /** @param class-string<Entity> $entityClass @return Repository<Entity> */
    public function repository(string $extensionId, string $entity, string $entityClass = Entity::class): Repository
    {
        return $this->repositories->make(ExtensionStorageKey::for($extensionId, $entity), $entityClass);
    }

    public function ensureDefinition(string $extensionId, string $entity, SchemaSpec $spec): DefinitionRef
    {
        return $this->definitions->ensure($extensionId, $entity, $spec);
    }
}
