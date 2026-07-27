<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Providers;

use Illuminate\Routing\Contracts\ControllerDispatcher;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Extensions\HostExtensionServices;
use Polymorph\Platform\Domain\Extensions\Http\ReplyAwareControllerDispatcher;
use Polymorph\Platform\Domain\Extensions\SdkBridge\Contracts\ExtensionRegistryState;
use Polymorph\Platform\Domain\Extensions\SdkBridge\DatabaseExtensionRegistryState;
use Polymorph\Platform\Domain\Extensions\SdkBridge\SdkActorDirectory;
use Polymorph\Platform\Domain\Extensions\SdkBridge\SdkCurrentActor;
use Polymorph\Platform\Domain\Extensions\SdkBridge\SdkExtensionState;
use Polymorph\Platform\Domain\Extensions\SdkBridge\SdkLogger;
use Polymorph\Platform\Domain\Extensions\SdkBridge\SdkRedactor;
use Polymorph\Platform\Domain\Extensions\SdkBridge\SdkRequestAuthenticator;
use Polymorph\Platform\Domain\Extensions\SdkBridge\SdkValidationConstraints;
use Polymorph\Sdk\Access\AccessGrants;
use Polymorph\Sdk\Contracts\ExtensionState;
use Polymorph\Sdk\Data\DefinitionRegistry;
use Polymorph\Sdk\Data\Repository;
use Polymorph\Sdk\Extension\ExtensionServices;
use Polymorph\Sdk\Identity\ActorDirectory;
use Polymorph\Sdk\Identity\CurrentActor;
use Polymorph\Sdk\Identity\RequestAuthenticator;
use Polymorph\Sdk\Logging\Logger;
use Polymorph\Sdk\Logging\Redactor;
use Polymorph\Sdk\Validation\ValidationConstraints;

/**
 * Биндинги контрактов Extension SDK V2 (`Polymorph\Sdk\*`) на host-адаптеры.
 *
 * Глобальные (не-скоупнутые) контракты резолвятся напрямую. Скоупнутые на
 * расширение (Repository/DefinitionRegistry/AccessGrants) НАМЕРЕННО не биндятся
 * глобально — только через {@see ExtensionServices} по явному ExtensionContext;
 * прямой глобальный резолв бросает внятную ошибку (как и в V1, но без footgun-а
 * со scopedConsumers).
 */
final class ExtensionsSdkServiceProvider extends ServiceProvider
{
    /**
     * Канонический каталог глобальных контрактов SDK V2 → адаптеры ядра.
     *
     * @var array<class-string, class-string>
     */
    public const SDK_CONTRACT_BINDINGS = [
        CurrentActor::class => SdkCurrentActor::class,
        ActorDirectory::class => SdkActorDirectory::class,
        RequestAuthenticator::class => SdkRequestAuthenticator::class,
        Logger::class => SdkLogger::class,
        Redactor::class => SdkRedactor::class,
        ExtensionState::class => SdkExtensionState::class,
        ExtensionServices::class => HostExtensionServices::class,
        ValidationConstraints::class => SdkValidationConstraints::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ExtensionRegistryState::class, DatabaseExtensionRegistryState::class);

        foreach (self::SDK_CONTRACT_BINDINGS as $contract => $adapter) {
            $this->app->singleton($contract, $adapter);
        }

        $guard = static function (string $contract): never {
            throw new \LogicException(
                "{$contract} is extension-scoped: resolve it via ExtensionServices->...(\$context) ".
                '(in an extension use $this->records()/$this->definitions()/$this->grants()).',
            );
        };

        $this->app->bind(Repository::class, static fn (): never => $guard(Repository::class));
        $this->app->bind(DefinitionRegistry::class, static fn (): never => $guard(DefinitionRegistry::class));
        $this->app->bind(AccessGrants::class, static fn (): never => $guard(AccessGrants::class));

        // Рендеринг нейтрального Reply, возвращённого контроллером расширения, в
        // HTTP-ответ. Глобальный биндинг ControllerDispatcher, но эффект только на
        // возврат типа Reply — контроллеры ядра не затрагиваются.
        $this->app->singleton(
            ControllerDispatcher::class,
            ReplyAwareControllerDispatcher::class,
        );
    }
}
