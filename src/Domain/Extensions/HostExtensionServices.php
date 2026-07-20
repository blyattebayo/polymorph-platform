<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessSubjectProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRuntime;
use Polymorph\Platform\Domain\DataPlatform\DataPlatform;
use Polymorph\Platform\Domain\DataPlatform\ScopedExtensionData;
use Polymorph\Platform\Domain\DataPlatform\SdkBridge\SdkDefinitionRegistry;
use Polymorph\Platform\Domain\Extensions\SdkBridge\SdkAccessGrants;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;
use Polymorph\Sdk\Access\AccessGrants;
use Polymorph\Sdk\Data\DefinitionRegistry;
use Polymorph\Sdk\Data\Entity;
use Polymorph\Sdk\Data\ExtensionData;
use Polymorph\Sdk\Data\Repository;
use Polymorph\Sdk\Extension\ExtensionContext;
use Polymorph\Sdk\Extension\ExtensionServices;

/**
 * Хост-реализация {@see ExtensionServices}: строит scoped-сервисы по ЯВНОМУ
 * {@see ExtensionContext}. Data-часть идёт через единый шов {@see DataPlatform};
 * гранты — через {@see SdkAccessGrants} с enforcement 'ext.{id}.'.
 */
final class HostExtensionServices implements ExtensionServices
{
    public function __construct(
        private readonly DataPlatform $platform,
        private readonly AccessControlAdministration $admin,
        private readonly PolicyRuntime $policyRuntime,
        private readonly AccessSubjectProvider $subjectProvider,
        private readonly UserRepository $users,
    ) {}

    /**
     * @param  class-string<Entity>  $entityClass
     * @return Repository<Entity>
     */
    public function repository(ExtensionContext $context, string $entity, string $entityClass = Entity::class): Repository
    {
        return $this->platform->repository($context->id->value, $entity, $entityClass);
    }

    public function data(ExtensionContext $context): ExtensionData
    {
        return new ScopedExtensionData($this->platform, $context->id->value);
    }

    public function definitions(ExtensionContext $context): DefinitionRegistry
    {
        return new SdkDefinitionRegistry($this->platform, $context);
    }

    public function grants(ExtensionContext $context): AccessGrants
    {
        return new SdkAccessGrants(
            $this->admin,
            $this->policyRuntime,
            $this->subjectProvider,
            $this->users,
            $context,
        );
    }
}
