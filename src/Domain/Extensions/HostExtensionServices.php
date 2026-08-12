<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\DataPlatform\DataPlatform;
use Polymorph\Platform\Domain\DataPlatform\ScopedExtensionData;
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

/**
 * Хост-реализация {@see ExtensionServices}: строит scoped-сервисы по ЯВНОМУ
 * {@see ExtensionContext}. Data-часть идёт через единый шов {@see DataPlatform};
 * гранты — через {@see SdkAccessGrants} с enforcement 'ext.{id}.'.
 *
 * The context must name an installed extension; otherwise access is denied.
 * ВАЖНО про модель угроз: плагины исполняются in-process как доверенный PHP-код,
 * поэтому ext-префиксы и эта сверка — защита от ошибок и случайного залезания в
 * чужой неймспейс, а не криптографическая граница. Полная атрибуция «какой плагин
 * сейчас исполняется» — этап «песочница расширений» (см. docs/ACCESS_CONTROL_AUDIT.md).
 */
final class HostExtensionServices implements ExtensionServices
{
    public function __construct(
        private readonly DataPlatform $platform,
        private readonly AccessControlAdministration $admin,
        private readonly AccessGate $gate,
        private readonly UserRepository $users,
        private readonly ExtensionDiscoveryService $discovery,
    ) {}

    /**
     * @param  class-string<Entity>  $entityClass
     * @return Repository<Entity>
     */
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

        return new SdkAccessGrants(
            $this->admin,
            $this->gate,
            $this->users,
            $context,
        );
    }

    private function assertKnown(ExtensionContext $context): void
    {
        if ($this->discovery->find($context->id->value) === null) {
            throw new \LogicException(sprintf(
                "ExtensionContext '%s' does not match an installed extension.",
                $context->id->value,
            ));
        }
    }
}
