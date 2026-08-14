<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\SdkBridge;

use Illuminate\Contracts\Container\Container;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCatalog;
use Polymorph\Sdk\Data\Entity;
use Polymorph\Sdk\Data\Repository;

final class SdkRecordRepositoryFactory
{
    public function __construct(
        private readonly SchemaCatalog $schemas,
        private readonly Container $container,
    ) {}

    /** @param class-string<Entity> $entityClass */
    public function make(ExtensionStorageKey $storageKey, string $entityClass = Entity::class): Repository
    {
        $definitionCode = $storageKey->value();
        $definition = $this->schemas->findDefinitionByCode($definitionCode);
        if ($definition === null) {
            throw DataPlatformResourceNotFound::for('data-definition', $definitionCode);
        }

        return $this->container->make(SdkRecordRepository::class, [
            'definitionId' => (int) $definition['id'],
            'entityClass' => $entityClass,
        ]);
    }
}
