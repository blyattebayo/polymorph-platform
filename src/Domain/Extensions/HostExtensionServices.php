<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessDenied;
use Polymorph\Platform\Domain\DataPlatform\SdkBridge\ExtensionDataGateway;
use Polymorph\Platform\Domain\DataPlatform\SdkBridge\ScopedExtensionData;
use Polymorph\Platform\Domain\DataPlatform\SdkBridge\SdkDefinitionRegistry;
use Polymorph\Platform\Domain\Extensions\SdkBridge\SdkAccessGrants;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;
use Polymorph\Platform\Domain\Users\Infrastructure\Repositories\UserRepository;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Sdk\Access\AccessGrants;
use Polymorph\Sdk\Data\DefinitionRegistry;
use Polymorph\Sdk\Data\Entity;
use Polymorph\Sdk\Data\ExtensionData;
use Polymorph\Sdk\Data\Repository;
use Polymorph\Sdk\Extension\ExtensionContext;
use Polymorph\Sdk\Extension\ExtensionServices;

/** Builds host SDK adapters for an explicit, installed extension context. */
final class HostExtensionServices implements ExtensionServices
{
    public function __construct(
        private readonly ExtensionDataGateway $platform,
        private readonly AccessControlAdministration $admin,
        private readonly AccessGate $gate,
        private readonly UserRepository $users,
        private readonly ExtensionDiscoveryService $discovery,
    ) {}

    /** @param class-string<Entity> $entityClass @return Repository<Entity> */
    public function repository(ExtensionContext $context, string $entity, string $entityClass = Entity::class): Repository
    {
        $this->assertKnown($context);

        return $this->platform->repository($context->id->value, $entity, $entityClass);
    }

    public function data(ExtensionContext $context): ExtensionData
    {
        $this->assertKnown($context);

        return new ScopedExtensionData($this->platform, $context->id->value);
    }

    public function definitions(ExtensionContext $context): DefinitionRegistry
    {
        $this->assertKnown($context);

        return new SdkDefinitionRegistry($this->platform, $context);
    }

    public function grants(ExtensionContext $context): AccessGrants
    {
        $this->assertKnown($context);

        return new SdkAccessGrants($this->admin, $this->gate, $this->users, $context);
    }

    private function assertKnown(ExtensionContext $context): void
    {
        if ($this->discovery->find($context->id->value) === null) {
            throw DataAccessDenied::for('extension.'.$context->id->value, 'use-sdk');
        }
    }
}
