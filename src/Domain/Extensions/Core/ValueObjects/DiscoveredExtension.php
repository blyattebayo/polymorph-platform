<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Core\ValueObjects;

final readonly class DiscoveredExtension
{
    /**
     * @param  list<ExtensionCapabilityDefinition>  $capabilityDefinitions
     * @param  list<string>  $defaultAdminRoles
     * @param  list<ExtensionRoleDefinition>  $pluginRoles  роли, объявленные расширением
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $sdkVersion,
        public string $manifestPath,
        public string $providerClass,
        public ?string $backendRouteFile,
        public array $capabilityDefinitions,
        public array $defaultAdminRoles,
        public bool $hasFrontend,
        public array $pluginRoles = [],
    ) {}
}
